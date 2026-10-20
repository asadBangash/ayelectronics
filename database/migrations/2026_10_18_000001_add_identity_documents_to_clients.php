<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('father_name')->nullable();
            $table->string('cnic_number', 32)->nullable();
            $table->string('photo_path')->nullable();
            $table->string('cnic_front_path')->nullable();
            $table->string('cnic_back_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['father_name', 'cnic_number', 'photo_path', 'cnic_front_path', 'cnic_back_path']);
        });
    }
};
