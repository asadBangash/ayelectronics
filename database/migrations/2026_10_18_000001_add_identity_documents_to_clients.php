<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'father_name')) {
                $table->string('father_name')->nullable();
            }
            if (! Schema::hasColumn('clients', 'cnic_number')) {
                $table->string('cnic_number', 32)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $cols = [];
            foreach (['father_name', 'cnic_number'] as $col) {
                if (Schema::hasColumn('clients', $col)) {
                    $cols[] = $col;
                }
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
