<?php

namespace App\Services\Installments;

use App\Models\Client;
use App\Models\InstallmentPlan;
use App\Models\InstallmentScheduleItem;
use App\Models\InstallmentSetting;
use Carbon\Carbon;

class CustomerOverdueRatingService
{
    public const LEVELS = [
        'green' => ['label' => 'Current — no overdue balance', 'months' => 0],
        'blue' => ['label' => '1 month overdue', 'months' => 1],
        'orange' => ['label' => '2 months overdue', 'months' => 2],
        'red' => ['label' => '3 months overdue', 'months' => 3],
        'dark_red' => ['label' => '4+ months overdue', 'months' => 4],
    ];

    public function refreshClientRating(int $clientId): string
    {
        $months = $this->oldestUnpaidOverdueMonths($clientId);
        $level = $this->levelForMonths($months);
        Client::where('id', $clientId)->update([
            'overdue_rating' => $level,
            'overdue_rating_at' => now(),
        ]);

        return $level;
    }

    public function oldestUnpaidOverdueMonths(int $clientId): int
    {
        $today = Carbon::today();
        $oldestDue = InstallmentScheduleItem::query()
            ->whereHas('plan', fn ($q) => $q->where('client_id', $clientId)->where('status', 'active'))
            ->where('remaining_amount', '>', 0)
            ->whereDate('due_date', '<', $today)
            ->orderBy('due_date')
            ->value('due_date');

        if (! $oldestDue) {
            return 0;
        }

        $due = Carbon::parse($oldestDue)->startOfDay();
        $months = $due->diffInMonths($today);
        if ($due->copy()->addMonths($months)->lt($today)) {
            $months++;
        }

        return max(0, (int) $months);
    }

    public function levelForMonths(int $months): string
    {
        if ($months <= 0) {
            return 'green';
        }
        if ($months === 1) {
            return 'blue';
        }
        if ($months === 2) {
            return 'orange';
        }
        if ($months === 3) {
            return 'red';
        }

        return 'dark_red';
    }

    public function labelForLevel(string $level): string
    {
        return self::LEVELS[$level]['label'] ?? $level;
    }

    public function thresholds(): array
    {
        $settings = InstallmentSetting::current();

        return $settings->rating_thresholds ?: [
            'blue' => 1,
            'orange' => 2,
            'red' => 3,
            'dark_red' => 4,
        ];
    }
}
