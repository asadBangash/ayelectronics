<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class InstallmentModule
{
    public static function isReady(): bool
    {
        return Schema::hasTable('installment_plans')
            && Schema::hasTable('installment_schedule_items')
            && Schema::hasColumn('clients', 'overdue_rating');
    }

    public static function setupHint(): string
    {
        return 'Installment tables are missing or incomplete. On this machine run: '
            .'php artisan migrate --force && php artisan db:seed --class=InstallmentPermissionsSeeder --force '
            .'&& php artisan config:clear && php artisan cache:clear';
    }
}
