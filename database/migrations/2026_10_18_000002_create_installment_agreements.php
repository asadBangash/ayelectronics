<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('installment_plans', function (Blueprint $table) {
            $table->id();
            $table->integer('sale_id')->unique();
            $table->integer('client_id');
            $table->decimal('sale_total', 18, 3);
            $table->decimal('advance_amount', 18, 3)->default(0);
            $table->unsignedSmallInteger('installment_count');
            $table->timestamps();
            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
            $table->foreign('client_id')->references('id')->on('clients')->restrictOnDelete();
        });
        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('installment_plan_id');
            $table->unsignedSmallInteger('number');
            $table->date('due_date');
            $table->decimal('amount', 18, 3);
            $table->decimal('paid_amount', 18, 3)->default(0);
            $table->string('status', 16)->default('unpaid');
            $table->timestamps();
            $table->foreign('installment_plan_id')->references('id')->on('installment_plans')->cascadeOnDelete();
            $table->unique(['installment_plan_id', 'number']);
        });
        Schema::create('installment_guarantors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('installment_plan_id');
            $table->unsignedTinyInteger('number');
            $table->string('full_name');
            $table->string('father_name');
            $table->string('cnic_number', 32);
            $table->string('mobile_number', 50);
            $table->text('address');
            $table->string('cnic_front_path');
            $table->string('cnic_back_path');
            $table->timestamps();
            $table->foreign('installment_plan_id')->references('id')->on('installment_plans')->cascadeOnDelete();
            $table->unique(['installment_plan_id', 'number']);
        });
        Schema::table('payment_sales', function (Blueprint $table) {
            $table->unsignedBigInteger('installment_id')->nullable()->index();
            $table->string('transaction_reference')->nullable();
            $table->string('installment_payment_method', 32)->nullable();
            $table->uuid('installment_request_key')->nullable()->unique();
            $table->foreign('installment_id')->references('id')->on('installments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_sales', function (Blueprint $table) {
            $table->dropForeign(['installment_id']);
            $table->dropColumn(['installment_id', 'transaction_reference']);
        });
        Schema::dropIfExists('installment_guarantors');
        Schema::dropIfExists('installments');
        Schema::dropIfExists('installment_plans');
    }
};
