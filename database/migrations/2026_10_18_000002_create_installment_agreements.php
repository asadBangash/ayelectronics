<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy filename kept for migration order. Payment-sale installment columns only;
 * installment plans/schedules are created in 2026_10_20_100000_create_installment_management_tables.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('payment_sales', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_sales', 'installment_id')) {
                $table->unsignedBigInteger('installment_id')->nullable()->index();
            }
            if (! Schema::hasColumn('payment_sales', 'transaction_reference')) {
                $table->string('transaction_reference')->nullable();
            }
            if (! Schema::hasColumn('payment_sales', 'installment_payment_method')) {
                $table->string('installment_payment_method', 32)->nullable();
            }
            if (! Schema::hasColumn('payment_sales', 'installment_request_key')) {
                $table->uuid('installment_request_key')->nullable()->unique();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_sales', function (Blueprint $table) {
            $cols = [];
            foreach (
                [
                    'installment_id',
                    'transaction_reference',
                    'installment_payment_method',
                    'installment_request_key',
                ] as $col
            ) {
                if (Schema::hasColumn('payment_sales', $col)) {
                    $cols[] = $col;
                }
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
