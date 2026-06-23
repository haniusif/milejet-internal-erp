<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Document expiry tracking (iqama / driving license / passport)
            $table->date('iqama_expiry_date')->nullable()->after('iqama_id');
            $table->date('license_expiry_date')->nullable()->after('iqama_expiry_date');
            $table->date('passport_expiry_date')->nullable()->after('passport_id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['iqama_expiry_date', 'license_expiry_date', 'passport_expiry_date']);
        });
    }
};
