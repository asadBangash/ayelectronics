<?php

namespace App\Console\Commands;

use App\Support\DemoDataCleaner;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

class ClearDemoDataCommand extends Command
{
    protected $signature = 'demo:clear
                            {--force : Run without confirmation}
                            {--all-documents : Delete every sale, purchase, quotation, expense, and non–walk-in customer (keeps users, roles, store theme, EL-* products)}
                            {--records-only : Only remove rows tracked in demo_records (UI Demo Data generator)}';

    protected $description = 'Remove demo sales, purchases, customers, and related data (keeps super admin, roles, and store settings)';

    public function handle(DemoDataCleaner $cleaner): int
    {
        if ($this->option('all-documents')) {
            $this->warn('This will delete ALL sales, purchases, quotations, expenses, suppliers, and customers except the walk-in customer.');
        } else {
            $this->line('Removes UI demo_records data and DemoDataSeeder rows ([DEMO], @demo.test, DEMO-*).');
            $this->line('Keeps: users, roles, permissions, settings, store_settings, EL-* catalog.');
        }

        if (! $this->option('force') && ! $this->confirm('Continue?', false)) {
            $this->comment('Cancelled.');

            return self::SUCCESS;
        }

        try {
            $counts = $cleaner->run([
                'all_documents' => (bool) $this->option('all-documents'),
                'records_only' => (bool) $this->option('records-only'),
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
