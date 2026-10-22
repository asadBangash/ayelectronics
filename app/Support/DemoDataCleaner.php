<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes demo customers, sales, purchases, and related rows while keeping
 * users, roles, permissions, settings, and store_settings (themes).
 */
class DemoDataCleaner
{
    /** Product codes created by ElectronicsDemoSeeder / other storefront demos. */
    private const STORE_DEMO_PRODUCT_PREFIXES = ['EL-%', 'TY-%', 'GR-%'];

    /** @var array<string, int> */
    private array $counts = [];

    /**
     * @param  array{all_documents?: bool, records_only?: bool, products?: bool, customers?: bool, store?: bool}  $options
     * @return array<string, int>
     */
    public function run(array $options = []): array
    {
        $this->counts = [];
        $allDocuments = (bool) ($options['all_documents'] ?? false);
        $recordsOnly = (bool) ($options['records_only'] ?? false);
        $clearProducts = (bool) ($options['products'] ?? false) || (bool) ($options['store'] ?? false);
        $clearCustomers = (bool) ($options['customers'] ?? false) || (bool) ($options['store'] ?? false);
        $catalogOnly = ($clearProducts || $clearCustomers)
            && ! $allDocuments
            && ! $recordsOnly
            && ! (bool) ($options['with_transactions'] ?? false);

        DB::transaction(function () use ($allDocuments, $recordsOnly, $clearProducts, $clearCustomers, $catalogOnly) {
            if ($allDocuments) {
                $this->purgeAllDocuments();
            } elseif ($recordsOnly) {
                $this->purgeDemoRecords();
            } elseif ($catalogOnly) {
                if ($clearProducts) {
                    $this->purgeStoreDemoProducts();
                }
                if ($clearCustomers) {
                    $this->purgeStoreDemoCustomers();
                }
            } else {
                $this->purgeDemoRecords();
                $this->purgeSeededDemo();
                if ($clearProducts) {
                    $this->purgeStoreDemoProducts();
                }
                if ($clearCustomers) {
                    $this->purgeStoreDemoCustomers();
                }
            }
        });

        return $this->counts;
    }

    /**
     * Same scope as System Settings → Demo Data → Reset.
     *
     * @return array<string, int>
     */
    public function purgeDemoRecordsOnly(): array
    {
        return $this->run(['records_only' => true]);
    }

    private function purgeDemoRecords(): void
    {
        $saleIds = $this->demoIds('sales');
        $this->deleteSalesByIds($saleIds);
        $this->addCount('sales', count($saleIds));

        $purchaseIds = $this->demoIds('purchases');
        $this->deletePurchasesByIds($purchaseIds);
        $this->addCount('purchases', count($purchaseIds));

        $quotationIds = $this->demoIds('quotations');
        DB::table('quotation_details')->whereIn('quotation_id', $quotationIds)->delete();
        $this->addCount('quotations', DB::table('quotations')->whereIn('id', $quotationIds)->delete());

        $expenseIds = $this->demoIds('expenses');
        $this->addCount('expenses', DB::table('expenses')->whereIn('id', $expenseIds)->delete());

        $productIds = $this->demoIds('products');
        DB::table('product_warehouse')->whereIn('product_id', $productIds)->delete();
        if (Schema::hasTable('product_images')) {
            DB::table('product_images')->whereIn('product_id', $productIds)->delete();
        }
        $this->addCount('products', DB::table('products')->whereIn('id', $productIds)->delete());

        $clientIds = $this->demoIds('clients');
        $this->deleteClientsByIds($clientIds);
        $this->addCount('clients', count($clientIds));

        $providerIds = $this->demoIds('providers');
        $this->addCount('providers', DB::table('providers')->whereIn('id', $providerIds)->delete());

        DB::table('categories')->whereIn('id', $this->demoIds('categories'))->delete();
        DB::table('expense_categories')->whereIn('id', $this->demoIds('expense_categories'))->delete();
        DB::table('units')->whereIn('id', $this->demoIds('units'))->delete();
        DB::table('warehouses')->whereIn('id', $this->demoIds('warehouses'))->delete();

        DB::table('demo_records')->delete();
    }

    private function purgeSeededDemo(): void
    {
        $demoClientIds = DB::table('clients')
            ->where(function ($q) {
                $q->where('name', 'like', '[DEMO]%')
                    ->orWhere('email', 'like', '%@demo.test');
            })
            ->pluck('id')
            ->all();

        $demoProviderIds = DB::table('providers')
            ->where(function ($q) {
                $q->where('name', 'like', '[DEMO]%')
                    ->orWhere('email', 'like', '%@demo.test');
            })
            ->pluck('id')
            ->all();

        $saleIds = DB::table('sales')
            ->where(function ($q) use ($demoClientIds) {
                if ($demoClientIds) {
                    $q->whereIn('client_id', $demoClientIds);
                }
                $q->orWhere('Ref', 'like', 'DEMO-%')
                    ->orWhere('Ref', 'like', 'SL_9%')
                    ->orWhere('notes', 'Demo data');
            })
            ->pluck('id')
            ->all();
        $this->deleteSalesByIds($saleIds);
        $this->addCount('seeded_sales', count($saleIds));

        $purchaseIds = DB::table('purchases')
            ->where(function ($q) use ($demoProviderIds) {
                if ($demoProviderIds) {
                    $q->whereIn('provider_id', $demoProviderIds);
                }
                $q->orWhere('Ref', 'like', 'DEMO-%')
                    ->orWhere('Ref', 'like', 'PR_9%')
                    ->orWhere('notes', 'Demo data');
            })
            ->pluck('id')
            ->all();
        $this->deletePurchasesByIds($purchaseIds);
        $this->addCount('seeded_purchases', count($purchaseIds));

        $quotationIds = DB::table('quotations')
            ->where('Ref', 'like', 'DEMO-%')
            ->orWhere('notes', 'Demo data')
            ->pluck('id')
            ->all();
        DB::table('quotation_details')->whereIn('quotation_id', $quotationIds)->delete();
        $this->addCount('seeded_quotations', DB::table('quotations')->whereIn('id', $quotationIds)->delete());

        $expenseDeleted = DB::table('expenses')
            ->where('details', 'like', '[DEMO]%')
            ->orWhere('Ref', 'like', 'EXP_9%')
            ->orWhere('Ref', 'like', 'DEMO-%')
            ->delete();
        $this->addCount('seeded_expenses', $expenseDeleted);

        DB::table('expense_categories')->where('name', 'like', '[DEMO]%')->delete();

        $this->deleteClientsByIds($demoClientIds);
        $this->addCount('seeded_clients', count($demoClientIds));

        $this->addCount('seeded_providers', DB::table('providers')->whereIn('id', $demoProviderIds)->delete());

        $demoProductIds = DB::table('products')
            ->where('code', 'like', 'DEMO-%')
            ->orWhere('name', 'like', '[DEMO]%')
            ->orWhere('note', 'Demo data')
            ->pluck('id')
            ->all();
        DB::table('product_warehouse')->whereIn('product_id', $demoProductIds)->delete();
        if (Schema::hasTable('product_images')) {
            DB::table('product_images')->whereIn('product_id', $demoProductIds)->delete();
        }
        $this->addCount('seeded_products', DB::table('products')->whereIn('id', $demoProductIds)->delete());

        DB::table('brands')->where('name', 'like', '[DEMO]%')->delete();
        DB::table('categories')->where('name', 'like', '[DEMO]%')->orWhere('code', 'like', 'DEMO-%')->delete();
        DB::table('warehouses')->where('name', 'like', '[DEMO]%')->delete();

        if (Schema::hasTable('accounts')) {
            DB::table('accounts')->where('account_name', 'like', '[DEMO]%')->delete();
        }
    }

    /** Electronics / Toys / Grocery storefront demo catalogs (EL-*, TY-*, GR-*). */
    private function purgeStoreDemoProducts(): void
    {
        $productIds = DB::table('products')
            ->where(function ($q) {
                foreach (self::STORE_DEMO_PRODUCT_PREFIXES as $prefix) {
                    $q->orWhere('code', 'like', $prefix);
                }
            })
            ->pluck('id')
            ->all();

        $this->deleteProductsByIds($productIds);
        $this->addCount('store_products', count($productIds));
    }

    /** Customers created by ElectronicsDemoSeeder / GroceryDemoSeeder (reviews, etc.). */
    private function purgeStoreDemoCustomers(): void
    {
        $keep = $this->walkInClientIds();

        $clientIds = DB::table('clients')
            ->when($keep, fn ($q) => $q->whereNotIn('id', $keep))
            ->where(function ($q) {
                $q->whereBetween('code', [97001, 99999])
                    ->orWhere(function ($q2) {
                        $q2->where('email', 'like', '%@example.com')
                            ->where('email', '!=', 'walk-in-customer@example.com');
                    });
            })
            ->pluck('id')
            ->all();

        $this->deleteClientsByIds($clientIds);
        $this->addCount('store_customers', count($clientIds));
    }

    /** @return array<int> */
    private function walkInClientIds(): array
    {
        return DB::table('clients')
            ->where('email', 'walk-in-customer@example.com')
            ->orWhere('name', 'walk-in-customer')
            ->pluck('id')
            ->all();
    }

    /** @param  array<int>  $productIds */
    private function deleteProductsByIds(array $productIds): void
    {
        if ($productIds === []) {
            return;
        }

        foreach (array_chunk($productIds, 200) as $chunk) {
            $saleIds = DB::table('sale_details')->whereIn('product_id', $chunk)->distinct()->pluck('sale_id')->all();
            $this->deleteSalesByIds($saleIds);

            $purchaseIds = DB::table('purchase_details')->whereIn('product_id', $chunk)->distinct()->pluck('purchase_id')->all();
            $this->deletePurchasesByIds($purchaseIds);

            $quotationIds = DB::table('quotation_details')->whereIn('product_id', $chunk)->distinct()->pluck('quotation_id')->all();
            if ($quotationIds) {
                DB::table('quotation_details')->whereIn('quotation_id', $quotationIds)->delete();
                DB::table('quotations')->whereIn('id', $quotationIds)->delete();
            }

            if (Schema::hasTable('draft_sale_details') && Schema::hasTable('draft_sales')) {
                $draftIds = DB::table('draft_sale_details')->whereIn('product_id', $chunk)->distinct()->pluck('draft_sale_id')->all();
                if ($draftIds) {
                    DB::table('draft_sale_details')->whereIn('draft_sale_id', $draftIds)->delete();
                    DB::table('draft_sales')->whereIn('id', $draftIds)->delete();
                }
            }

            if (Schema::hasTable('product_reviews')) {
                DB::table('product_reviews')->whereIn('product_id', $chunk)->delete();
            }

            if (Schema::hasTable('product_variants')) {
                DB::table('product_variants')->whereIn('product_id', $chunk)->delete();
            }

            DB::table('product_warehouse')->whereIn('product_id', $chunk)->delete();
            if (Schema::hasTable('product_images')) {
                DB::table('product_images')->whereIn('product_id', $chunk)->delete();
            }

            DB::table('products')->whereIn('id', $chunk)->delete();
        }
    }

    private function purgeAllDocuments(): void
    {
        $saleIds = DB::table('sales')->pluck('id')->all();
        $this->deleteSalesByIds($saleIds);
        $this->addCount('sales', count($saleIds));

        $purchaseIds = DB::table('purchases')->pluck('id')->all();
        $this->deletePurchasesByIds($purchaseIds);
        $this->addCount('purchases', count($purchaseIds));

        DB::table('quotation_details')->delete();
        $this->addCount('quotations', DB::table('quotations')->delete());

        $this->addCount('expenses', DB::table('expenses')->delete());

        if (Schema::hasTable('draft_sale_details')) {
            DB::table('draft_sale_details')->delete();
        }
        if (Schema::hasTable('draft_sales')) {
            DB::table('draft_sales')->delete();
        }

        $keepClientIds = DB::table('clients')
            ->where('email', 'walk-in-customer@example.com')
            ->orWhere('name', 'walk-in-customer')
            ->pluck('id')
            ->all();

        $clientIds = DB::table('clients')
            ->when($keepClientIds, fn ($q) => $q->whereNotIn('id', $keepClientIds))
            ->pluck('id')
            ->all();
        $this->deleteClientsByIds($clientIds);
        $this->addCount('clients', count($clientIds));

        $this->addCount('providers', DB::table('providers')->delete());

        DB::table('demo_records')->delete();
    }

    /** @param  array<int>  $saleIds */
    private function deleteSalesByIds(array $saleIds): void
    {
        if ($saleIds === []) {
            return;
        }

        foreach (array_chunk($saleIds, 500) as $chunk) {
            $this->deleteInstallmentsForSaleIds($chunk);
            $this->deleteSaleReturnsForSaleIds($chunk);

            DB::table('payment_sales')->whereIn('sale_id', $chunk)->delete();
            DB::table('sale_details')->whereIn('sale_id', $chunk)->delete();
            DB::table('sales')->whereIn('id', $chunk)->delete();
        }
    }

    /** @param  array<int>  $purchaseIds */
    private function deletePurchasesByIds(array $purchaseIds): void
    {
        if ($purchaseIds === []) {
            return;
        }

        foreach (array_chunk($purchaseIds, 500) as $chunk) {
            DB::table('payment_purchases')->whereIn('purchase_id', $chunk)->delete();
            DB::table('purchase_details')->whereIn('purchase_id', $chunk)->delete();
            DB::table('purchases')->whereIn('id', $chunk)->delete();
        }
    }

    /** @param  array<int>  $clientIds */
    private function deleteClientsByIds(array $clientIds): void
    {
        if ($clientIds === []) {
            return;
        }

        foreach (array_chunk($clientIds, 500) as $chunk) {
            $saleIds = DB::table('sales')->whereIn('client_id', $chunk)->pluck('id')->all();
            $this->deleteSalesByIds($saleIds);

            if (Schema::hasTable('product_reviews')) {
                DB::table('product_reviews')->whereIn('client_id', $chunk)->delete();
            }

            if (Schema::hasTable('online_orders')) {
                $orderIds = DB::table('online_orders')->whereIn('client_id', $chunk)->pluck('id')->all();
                $this->deleteOnlineOrdersByIds($orderIds);
            }

            if (Schema::hasTable('client_documents')) {
                DB::table('client_documents')->whereIn('client_id', $chunk)->delete();
            }
            if (Schema::hasTable('draft_sale_details') && Schema::hasTable('draft_sales')) {
                $draftIds = DB::table('draft_sales')->whereIn('client_id', $chunk)->pluck('id')->all();
                if ($draftIds) {
                    DB::table('draft_sale_details')->whereIn('draft_sale_id', $draftIds)->delete();
                    DB::table('draft_sales')->whereIn('id', $draftIds)->delete();
                }
            }
            $returnIds = DB::table('sale_returns')->whereIn('client_id', $chunk)->pluck('id')->all();
            $this->deleteSaleReturnsByIds($returnIds);

            DB::table('clients')->whereIn('id', $chunk)->delete();
        }
    }

    /** @param  array<int>  $saleIds */
    private function deleteSaleReturnsForSaleIds(array $saleIds): void
    {
        if (! Schema::hasTable('sale_returns')) {
            return;
        }

        $returnIds = DB::table('sale_returns')->whereIn('sale_id', $saleIds)->pluck('id')->all();
        $this->deleteSaleReturnsByIds($returnIds);
    }

    /** @param  array<int>  $returnIds */
    private function deleteSaleReturnsByIds(array $returnIds): void
    {
        if ($returnIds === [] || ! Schema::hasTable('sale_returns')) {
            return;
        }

        foreach (array_chunk($returnIds, 500) as $chunk) {
            if (Schema::hasTable('payment_sale_returns')) {
                DB::table('payment_sale_returns')->whereIn('sale_return_id', $chunk)->delete();
            }
            if (Schema::hasTable('sale_return_detail_batches')) {
                $detailIds = DB::table('sale_return_details')->whereIn('sale_return_id', $chunk)->pluck('id')->all();
                if ($detailIds) {
                    DB::table('sale_return_detail_batches')->whereIn('sale_return_detail_id', $detailIds)->delete();
                }
            }
            if (Schema::hasTable('sale_return_details')) {
                DB::table('sale_return_details')->whereIn('sale_return_id', $chunk)->delete();
            }
            DB::table('sale_returns')->whereIn('id', $chunk)->delete();
        }
    }

    /** @param  array<int>  $saleIds */
    private function deleteInstallmentsForSaleIds(array $saleIds): void
    {
        if ($saleIds === [] || ! Schema::hasTable('installment_plans')) {
            return;
        }

        foreach (array_chunk($saleIds, 200) as $chunk) {
            $planIds = DB::table('installment_plans')->whereIn('sale_id', $chunk)->pluck('id')->all();
            if ($planIds === []) {
                if (Schema::hasTable('sale_guarantors')) {
                    DB::table('sale_guarantors')->whereIn('sale_id', $chunk)->delete();
                }

                continue;
            }

            $scheduleIds = DB::table('installment_schedule_items')
                ->whereIn('installment_plan_id', $planIds)
                ->pluck('id')
                ->all();

            if ($scheduleIds && Schema::hasTable('installment_payment_allocations')) {
                DB::table('installment_payment_allocations')
                    ->whereIn('installment_schedule_item_id', $scheduleIds)
                    ->delete();
            }

            $paymentIds = DB::table('payment_sales')->whereIn('sale_id', $chunk)->pluck('id')->all();
            if ($paymentIds && Schema::hasTable('installment_payment_allocations')) {
                DB::table('installment_payment_allocations')
                    ->whereIn('payment_sale_id', $paymentIds)
                    ->delete();
            }

            DB::table('installment_schedule_items')->whereIn('installment_plan_id', $planIds)->delete();

            if (Schema::hasTable('installment_whatsapp_messages')) {
                DB::table('installment_whatsapp_messages')->whereIn('installment_plan_id', $planIds)->delete();
            }

            DB::table('installment_plans')->whereIn('id', $planIds)->delete();

            if (Schema::hasTable('sale_guarantors')) {
                DB::table('sale_guarantors')->whereIn('sale_id', $chunk)->delete();
            }
        }
    }

    /** @param  array<int>  $orderIds */
    private function deleteOnlineOrdersByIds(array $orderIds): void
    {
        if ($orderIds === [] || ! Schema::hasTable('online_orders')) {
            return;
        }

        foreach (array_chunk($orderIds, 200) as $chunk) {
            if (Schema::hasTable('store_coupon_redemptions')) {
                DB::table('store_coupon_redemptions')->whereIn('online_order_id', $chunk)->delete();
            }
            if (Schema::hasTable('online_order_items')) {
                DB::table('online_order_items')->whereIn('order_id', $chunk)->delete();
            }
            DB::table('online_orders')->whereIn('id', $chunk)->delete();
        }
    }

    /** @return array<int> */
    private function demoIds(string $type): array
    {
        if (! Schema::hasTable('demo_records')) {
            return [];
        }

        return DB::table('demo_records')->where('record_type', $type)->pluck('record_id')->all();
    }

    private function addCount(string $key, int $delta): void
    {
        if ($delta <= 0) {
            return;
        }
        $this->counts[$key] = ($this->counts[$key] ?? 0) + $delta;
    }

}
