<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('guarantors')) {
            Schema::table('guarantors', function (Blueprint $table) {
                if (! Schema::hasColumn('guarantors', 'father_name')) {
                    $table->string('father_name', 191)->nullable()->after('full_name');
                }
                if (! Schema::hasColumn('guarantors', 'email')) {
                    $table->string('email', 191)->nullable()->after('whatsapp');
                }
                if (! Schema::hasColumn('guarantors', 'permanent_address')) {
                    $table->text('permanent_address')->nullable()->after('address');
                }
                if (! Schema::hasColumn('guarantors', 'business_address')) {
                    $table->text('business_address')->nullable()->after('permanent_address');
                }
            });
        }

        if (! Schema::hasTable('client_guarantors')) {
            Schema::create('client_guarantors', function (Blueprint $table) {
                $table->id();
                $table->integer('client_id');
                $table->unsignedBigInteger('guarantor_id');
                $table->unsignedTinyInteger('position')->default(1);
                $table->string('relationship', 120)->nullable();
                $table->timestamps();

                $table->unique(['client_id', 'position'], 'client_guar_client_pos_uq');
                $table->index('client_id');
                $table->index('guarantor_id');
                $table->foreign('guarantor_id', 'client_guar_guar_fk')
                    ->references('id')->on('guarantors')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_guarantors');

        if (Schema::hasTable('guarantors')) {
            Schema::table('guarantors', function (Blueprint $table) {
                foreach (['father_name', 'email', 'permanent_address', 'business_address'] as $col) {
                    if (Schema::hasColumn('guarantors', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
