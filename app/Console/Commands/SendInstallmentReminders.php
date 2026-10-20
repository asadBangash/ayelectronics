<?php

namespace App\Console\Commands;

use App\Models\InstallmentScheduleItem;
use App\Models\Sale;
use App\Services\Installments\InstallmentWhatsAppService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendInstallmentReminders extends Command
{
    protected $signature = 'installments:send-reminders {--dry-run}';

    protected $description = 'Send WhatsApp installment reminders for due and overdue schedule items';

    public function handle(InstallmentWhatsAppService $whatsapp): int
    {
        $today = Carbon::today();
        $items = InstallmentScheduleItem::query()
            ->with('plan.sale')
            ->where('remaining_amount', '>', 0)
            ->whereDate('due_date', '<=', $today)
            ->get();

        $count = 0;
        foreach ($items as $item) {
            $saleId = $item->plan?->sale_id;
            if (! $saleId) {
                continue;
            }
            if ($this->option('dry-run')) {
                $this->line("Would remind sale #{$saleId} installment #{$item->installment_number}");
                $count++;

                continue;
            }
            try {
                $whatsapp->sendReminder($saleId, 'scheduled_reminder');
                $count++;
            } catch (\Throwable $e) {
                $this->warn("Sale {$saleId}: ".$e->getMessage());
            }
        }

        $this->info("Processed {$count} reminder(s).");

        return self::SUCCESS;
    }
}
