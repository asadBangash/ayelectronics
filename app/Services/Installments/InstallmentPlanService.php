<?php

namespace App\Services\Installments;

use App\Models\Guarantor;
use App\Models\InstallmentAuditLog;
use App\Models\InstallmentPlan;
use App\Models\InstallmentScheduleItem;
use App\Models\InstallmentSetting;
use App\Models\PaymentSale;
use App\Models\Sale;
use App\Models\SaleGuarantor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InstallmentPlanService
{
    public function __construct(
        protected CustomerOverdueRatingService $ratingService
    ) {}

    public function createForSale(Sale $sale, array $payload): InstallmentPlan
    {
        if ($sale->installmentPlan) {
            throw new InvalidArgumentException('This sale already has an installment plan.');
        }

        $settings = InstallmentSetting::current();
        $down = round((float) ($payload['down_payment'] ?? 0), 2);
        $months = (int) ($payload['duration_months'] ?? 0);
        $markupPercent = (float) ($payload['markup_percent'] ?? $settings->default_markup_percent);
        $dueDay = (int) ($payload['due_day_of_month'] ?? $settings->due_day_of_month);
        $dueDay = max(1, min(28, $dueDay));

        if ($months < 1) {
            throw new InvalidArgumentException('Installment duration must be at least 1 month.');
        }

        $total = round((float) $sale->GrandTotal, 2);
        if ($down < 0 || $down > $total) {
            throw new InvalidArgumentException('Invalid down payment amount.');
        }

        $financed = round($total - $down, 2);
        $markupAmount = round($financed * ($markupPercent / 100), 2);
        $totalPayable = round($financed + $markupAmount, 2);
        $monthly = round($totalPayable / $months, 2);
        $remainder = round($totalPayable - ($monthly * $months), 2);

        $firstDue = isset($payload['first_due_date'])
            ? Carbon::parse($payload['first_due_date'])
            : Carbon::today()->addMonth()->day(min($dueDay, 28));

        return DB::transaction(function () use ($sale, $payload, $settings, $down, $months, $markupPercent, $markupAmount, $total, $financed, $totalPayable, $monthly, $remainder, $firstDue, $dueDay) {
            if ($settings->require_guarantors_for_installment) {
                $this->assertGuarantorsProvided($sale->id, $payload, (int) $settings->guarantors_required);
            }

            $plan = InstallmentPlan::create([
                'sale_id' => $sale->id,
                'client_id' => $sale->client_id,
                'total_amount' => $total,
                'down_payment' => $down,
                'financed_amount' => $financed,
                'markup_percent' => $markupPercent,
                'markup_amount' => $markupAmount,
                'total_payable' => $totalPayable,
                'duration_months' => $months,
                'monthly_installment' => $monthly,
                'due_day_of_month' => $dueDay,
                'first_due_date' => $firstDue->toDateString(),
                'next_due_date' => $firstDue->toDateString(),
                'total_paid' => 0,
                'outstanding_balance' => $totalPayable,
                'status' => 'active',
                'internal_notes' => $payload['internal_notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $schedule = [];
            for ($i = 1; $i <= $months; $i++) {
                $due = $firstDue->copy()->addMonths($i - 1);
                $amount = $monthly;
                if ($i === $months) {
                    $amount = round($monthly + $remainder, 2);
                }
                $schedule[] = [
                    'installment_plan_id' => $plan->id,
                    'installment_number' => $i,
                    'due_date' => $due->toDateString(),
                    'amount_due' => $amount,
                    'amount_paid' => 0,
                    'remaining_amount' => $amount,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            InstallmentScheduleItem::insert($schedule);

            $sale->update(['is_installment_sale' => true]);
            $this->syncPlanTotals($plan);
            $this->attachGuarantors($sale->id, $payload['guarantors'] ?? []);
            $this->log('installment_plan', $plan->id, 'created', null, $plan->toArray());

            $this->ratingService->refreshClientRating((int) $sale->client_id);

            return $plan->fresh(['scheduleItems']);
        });
    }

    protected function assertGuarantorsProvided(int $saleId, array $payload, int $required): void
    {
        $guarantors = $payload['guarantors'] ?? [];
        if (count($guarantors) < $required) {
            throw new InvalidArgumentException("This installment sale requires {$required} guarantor(s).");
        }
    }

    protected function attachGuarantors(int $saleId, array $guarantors): void
    {
        foreach ($guarantors as $index => $row) {
            $position = (int) ($row['position'] ?? ($index + 1));
            $guarantorId = $row['guarantor_id'] ?? null;
            if (! $guarantorId && ! empty($row['guarantor'])) {
                $guarantorId = $this->findOrCreateGuarantor($row['guarantor'])->id;
            }
            if (! $guarantorId) {
                continue;
            }
            SaleGuarantor::updateOrCreate(
                ['sale_id' => $saleId, 'position' => $position],
                ['guarantor_id' => $guarantorId, 'relationship' => $row['relationship'] ?? null]
            );
        }
    }

    protected function findOrCreateGuarantor(array $data): Guarantor
    {
        $cnic = \App\Support\PakistanValidators::normalizeCnic($data['cnic'] ?? null);
        if ($cnic) {
            $existing = Guarantor::where('cnic', $cnic)->first();
            if ($existing) {
                return $existing;
            }
        }

        return Guarantor::create([
            'full_name' => $data['full_name'],
            'cnic' => $cnic,
            'mobile' => $data['mobile'] ?? null,
            'whatsapp' => $data['whatsapp'] ?? null,
            'phone_secondary' => $data['phone_secondary'] ?? null,
            'address' => $data['address'] ?? null,
            'occupation' => $data['occupation'] ?? null,
        ]);
    }

    public function allocatePayment(PaymentSale $payment): void
    {
        $sale = $payment->sale;
        if (! $sale || ! $sale->installmentPlan) {
            return;
        }

        $plan = $sale->installmentPlan;
        $remaining = round((float) $payment->montant, 2);
        if ($remaining <= 0) {
            return;
        }

        $items = $plan->scheduleItems()
            ->where('remaining_amount', '>', 0)
            ->orderBy('due_date')
            ->orderBy('installment_number')
            ->lockForUpdate()
            ->get();

        foreach ($items as $item) {
            if ($remaining <= 0) {
                break;
            }
            $exists = $item->allocations()->where('payment_sale_id', $payment->id)->exists();
            if ($exists) {
                continue;
            }
            $apply = min($remaining, (float) $item->remaining_amount);
            $item->allocations()->create([
                'payment_sale_id' => $payment->id,
                'amount' => $apply,
                'transaction_reference' => $payment->Ref,
            ]);
            $item->amount_paid = round((float) $item->amount_paid + $apply, 2);
            $item->remaining_amount = round((float) $item->amount_due - (float) $item->amount_paid, 2);
            if ($item->remaining_amount <= 0) {
                $item->remaining_amount = 0;
                $item->status = 'paid';
                $item->paid_at = $payment->date ?? now()->toDateString();
            } elseif ($item->amount_paid > 0) {
                $item->status = 'partial';
            }
            $item->save();
            $remaining = round($remaining - $apply, 2);
        }

        $this->syncPlanTotals($plan);
        $this->ratingService->refreshClientRating((int) $plan->client_id);
    }

    public function syncPlanTotals(InstallmentPlan $plan): InstallmentPlan
    {
        $paid = (float) $plan->scheduleItems()->sum('amount_paid');
        $outstanding = (float) $plan->scheduleItems()->sum('remaining_amount');
        $nextDue = $plan->scheduleItems()
            ->where('remaining_amount', '>', 0)
            ->orderBy('due_date')
            ->value('due_date');

        $today = Carbon::today();
        $overdueAmount = (float) $plan->scheduleItems()
            ->where('remaining_amount', '>', 0)
            ->whereDate('due_date', '<', $today)
            ->sum('remaining_amount');

        $oldestOverdue = $plan->scheduleItems()
            ->where('remaining_amount', '>', 0)
            ->whereDate('due_date', '<', $today)
            ->orderBy('due_date')
            ->value('due_date');

        $overdueDays = $oldestOverdue
            ? Carbon::parse($oldestOverdue)->diffInDays($today)
            : 0;

        $plan->update([
            'total_paid' => $paid,
            'outstanding_balance' => $outstanding,
            'next_due_date' => $nextDue,
            'overdue_amount' => $overdueAmount,
            'overdue_days' => $overdueDays,
            'status' => $outstanding <= 0 ? 'completed' : 'active',
        ]);

        return $plan->fresh();
    }

    protected function log(string $type, int $id, string $action, ?array $old, ?array $new): void
    {
        InstallmentAuditLog::create([
            'auditable_type' => $type,
            'auditable_id' => $id,
            'action' => $action,
            'old_values' => $old,
            'new_values' => $new,
            'user_id' => Auth::id(),
        ]);
    }
}
