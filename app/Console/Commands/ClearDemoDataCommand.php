<?php

namespace App\Console\Commands;

use App\Support\DemoDataCleaner;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

class ClearDemoDataCommand extends Command
{
    protected $signature = 'demo:clear
                            {--force : Run without confirmation}
                            {--store : Remove storefront demo products (EL-*, TY-*, GR-*) and demo customers (keeps walk-in + store theme)}
                            {--products : Remove storefront demo products only}
                            {--customers : Remove storefront demo customers only (codes 97001–99999, *@example.com except walk-in)}
                            {--with-transactions : With --store/--products/--customers, also run default demo sales/purchase cleanup}
                            {--all-documents : Delete every sale, purchase, quotation, expense, and non–walk-in customer (keeps users, roles, store theme)}
                            {--records-only : Only remove rows tracked in demo_records (UI Demo Data generator)}';

    protected $description = 'Remove demo sales, purchases, customers, products, and related data (keeps super admin, roles, and store settings)';

    public function handle(DemoDataCleaner $cleaner): int
    {
        $store = (bool) $this->option('store');
        $products = (bool) $this->option('products') || $store;
        $customers = (bool) $this->option('customers') || $store;

        if ($this->option('all-documents')) {
            $this->warn('This will delete ALL sales, purchases, quotations, expenses, suppliers, and customers except the walk-in customer.');
        } elseif ($products || $customers) {
            $this->line('Storefront cleanup (keeps store_settings / theme, categories, brands, super admin):');
            if ($products) {
                $this->line('  • Demo products: EL-*, TY-*, GR-*');
            }
            if ($customers) {
                $this->line('  • Demo customers: seeder codes 97001–99999 and *@example.com (not walk-in)');
            }
        } else {
            $this->line('Removes UI demo_records data and DemoDataSeeder rows ([DEMO], @demo.test, DEMO-*).');
            $this->line('Keeps: users, roles, permissions, settings, store_settings, EL-* catalog (use --store to remove EL-*).');
        }

        if (! $this->option('force') && ! $this->confirm('Continue?', false)) {
            $this->comment('Cancelled.');

            return self::SUCCESS;
        }

        try {
            $counts = $cleaner->run([
                'all_documents' => (bool) $this->option('all-documents'),
                'records_only' => (bool) $this->option('records-only'),
                'store' => $store,
                'products' => $products,
                'customers' => $customers,
                'with_transactions' => (bool) $this->option('with-transactions'),
            ]);
        } catch (QueryException $e) {
            report($e);
            $this->error('Cleanup failed: '.$e->getMessage());
            $this->line('Some demo rows may still be referenced by real data. Fix references or use --records-only.');

            return self::FAILURE;
        }

        if ($counts === []) {
            $this->info('Nothing to remove — database already has no matching demo data.');
        } else {
            $this->info('Demo cleanup finished:');
            foreach ($counts as $type => $n) {
                $this->line("  {$type}: {$n}");
            }
        }

        $this->newLine();
        $this->comment('Tip: php artisan cache:clear && php artisan config:clear');

        return self::SUCCESS;
    }
}
