<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contract terms captured on the employee wizard: fixed-term duration,
 * work schedule (full/part time…), notice & probation periods, auto-renewal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedSmallInteger('contract_duration_months')->nullable()->after('contract_type');
            $table->string('work_schedule', 32)->nullable()->after('contract_duration_months');
            $table->unsignedSmallInteger('notice_period_days')->nullable()->after('work_schedule');
            $table->unsignedSmallInteger('probation_period_days')->nullable()->after('notice_period_days');
            $table->boolean('auto_renewal')->nullable()->after('probation_period_days');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'contract_duration_months', 'work_schedule',
                'notice_period_days', 'probation_period_days', 'auto_renewal',
            ]);
        });
    }
};
