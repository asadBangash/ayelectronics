<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'cnic')) {
                $table->string('cnic', 15)->nullable()->after('phone');
                $table->index('cnic');
            }
            if (! Schema::hasColumn('clients', 'whatsapp')) {
                $table->string('whatsapp', 20)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('clients', 'phone_secondary')) {
                $table->string('phone_secondary', 20)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('clients', 'area')) {
                $table->string('area', 120)->nullable()->after('city');
            }
            if (! Schema::hasColumn('clients', 'occupation')) {
                $table->string('occupation', 191)->nullable()->after('city');
            }
            if (! Schema::hasColumn('clients', 'photo_path')) {
                $table->string('photo_path', 255)->nullable();
            }
            if (! Schema::hasColumn('clients', 'cnic_front_path')) {
                $table->string('cnic_front_path', 255)->nullable();
            }
            if (! Schema::hasColumn('clients', 'cnic_back_path')) {
                $table->string('cnic_back_path', 255)->nullable();
            }
            if (! Schema::hasColumn('clients', 'overdue_rating')) {
                $table->string('overdue_rating', 32)->default('green');
                $table->index('overdue_rating');
            }
            if (! Schema::hasColumn('clients', 'overdue_rating_at')) {
                $table->timestamp('overdue_rating_at')->nullable();
            }
        });

        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'is_installment_sale')) {
                $table->boolean('is_installment_sale')->default(false)->after('payment_statut');
                $table->index('is_installment_sale');
            }
        });

        Schema::create('installment_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('require_guarantors_for_installment')->default(true);
            $table->unsignedTinyInteger('guarantors_required')->default(2);
            $table->unsignedTinyInteger('due_day_of_month')->default(1);
            $table->decimal('default_markup_percent', 8, 4)->default(0);
            $table->json('rating_thresholds')->nullable();
            $table->boolean('whatsapp_enabled')->default(false);
            $table->boolean('whatsapp_test_mode')->default(true);
            $table->string('whatsapp_api_url', 255)->nullable();
            $table->text('whatsapp_access_token')->nullable();
            $table->string('whatsapp_phone_number_id', 64)->nullable();
            $table->string('whatsapp_business_account_id', 64)->nullable();
            $table->string('whatsapp_webhook_verify_token', 64)->nullable();
            $table->json('whatsapp_reminder_rules')->nullable();
            $table->timestamps();
        });

        Schema::create('guarantors', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('cnic', 15)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('phone_secondary', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('occupation', 191)->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->string('cnic_front_path', 255)->nullable();
            $table->string('cnic_back_path', 255)->nullable();
            $table->string('verification_status', 32)->default('pending');
            $table->text('verification_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('cnic');
        });

        Schema::create('guarantor_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('guarantor_id');
            $table->string('label', 120)->nullable();
            $table->string('path', 255);
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
            $table->foreign('guarantor_id', 'guar_doc_guar_fk')->references('id')->on('guarantors')->cascadeOnDelete();
        });

        Schema::create('client_documents', function (Blueprint $table) {
            $table->id();
            $table->integer('client_id');
            $table->string('type', 64)->default('supporting');
            $table->string('label', 120)->nullable();
            $table->string('path', 255);
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
            $table->index('client_id');
        });

        Schema::create('installment_plans', function (Blueprint $table) {
            $table->id();
            $table->integer('sale_id')->unique();
            $table->integer('client_id');
            $table->decimal('total_amount', 15, 2);
            $table->decimal('down_payment', 15, 2)->default(0);
            $table->decimal('financed_amount', 15, 2);
            $table->decimal('markup_percent', 8, 4)->default(0);
            $table->decimal('markup_amount', 15, 2)->default(0);
            $table->decimal('total_payable', 15, 2);
            $table->unsignedSmallInteger('duration_months');
            $table->decimal('monthly_installment', 15, 2);
            $table->unsignedTinyInteger('due_day_of_month')->default(1);
            $table->date('first_due_date')->nullable();
            $table->date('next_due_date')->nullable();
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->decimal('outstanding_balance', 15, 2)->default(0);
            $table->unsignedInteger('overdue_days')->default(0);
            $table->decimal('overdue_amount', 15, 2)->default(0);
            $table->string('status', 32)->default('active');
            $table->text('internal_notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'status']);
            $table->index('next_due_date');
        });

        Schema::create('installment_schedule_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('installment_plan_id');
            $table->unsignedSmallInteger('installment_number');
            $table->date('due_date');
            $table->decimal('amount_due', 15, 2);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2);
            $table->string('status', 32)->default('pending');
            $table->date('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['installment_plan_id', 'installment_number'], 'inst_sched_plan_num_uq');
            $table->index(['due_date', 'status']);
            $table->foreign('installment_plan_id', 'inst_sched_plan_fk')->references('id')->on('installment_plans')->cascadeOnDelete();
        });

        Schema::create('installment_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('installment_schedule_item_id');
            $table->integer('payment_sale_id');
            $table->decimal('amount', 15, 2);
            $table->string('transaction_reference', 120)->nullable();
            $table->timestamps();
            $table->unique(['payment_sale_id', 'installment_schedule_item_id'], 'inst_pay_alloc_uq');
            $table->index('payment_sale_id');
            $table->foreign('installment_schedule_item_id', 'inst_pay_sched_fk')->references('id')->on('installment_schedule_items')->cascadeOnDelete();
        });

        Schema::create('sale_guarantors', function (Blueprint $table) {
            $table->id();
            $table->integer('sale_id');
            $table->unsignedBigInteger('guarantor_id');
            $table->unsignedTinyInteger('position');
            $table->string('relationship', 120)->nullable();
            $table->timestamps();
            $table->unique(['sale_id', 'position']);
            $table->index('sale_id');
            $table->foreign('guarantor_id', 'sale_guar_guar_fk')->references('id')->on('guarantors');
        });

        Schema::create('installment_whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->integer('client_id')->nullable();
            $table->integer('sale_id')->nullable();
            $table->unsignedBigInteger('installment_plan_id')->nullable();
            $table->string('template_key', 64)->nullable();
            $table->string('to_number', 32);
            $table->text('body');
            $table->string('provider_message_id', 120)->nullable();
            $table->string('status', 32)->default('queued');
            $table->json('provider_response')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'sale_id']);
            $table->index('status');
            $table->foreign('installment_plan_id', 'inst_wa_plan_fk')->references('id')->on('installment_plans')->nullOnDelete();
        });

        Schema::create('installment_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('auditable_type', 64);
            $table->unsignedBigInteger('auditable_id');
            $table->string('action', 64);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_audit_logs');
        Schema::dropIfExists('installment_whatsapp_messages');
        Schema::dropIfExists('sale_guarantors');
        Schema::dropIfExists('installment_payment_allocations');
        Schema::dropIfExists('installment_schedule_items');
        Schema::dropIfExists('installment_plans');
        Schema::dropIfExists('client_documents');
        Schema::dropIfExists('guarantor_documents');
        Schema::dropIfExists('guarantors');
        Schema::dropIfExists('installment_settings');

        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'is_installment_sale')) {
                $table->dropColumn('is_installment_sale');
            }
        });

        Schema::table('clients', function (Blueprint $table) {
            foreach (['cnic', 'whatsapp', 'phone_secondary', 'area', 'occupation', 'photo_path', 'cnic_front_path', 'cnic_back_path', 'overdue_rating', 'overdue_rating_at'] as $col) {
                if (Schema::hasColumn('clients', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
