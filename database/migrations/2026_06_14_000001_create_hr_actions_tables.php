<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Local cache of Odoo hr.salary.adjustment (mj_hr_actions addon).
        Schema::create('salary_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('odoo_employee_id')->index();
            $table->string('employee_name')->nullable();
            $table->string('kind', 16)->index(); // penalty | deduction | reward | allowance
            $table->date('date')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('reason')->nullable();
            $table->string('state', 16)->default('draft')->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // Local cache of Odoo hr.payslip.payment (partial settlement log).
        Schema::create('payslip_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->unsignedBigInteger('odoo_payslip_id')->index();
            $table->date('date')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('method', 16)->nullable();
            $table->string('reference')->nullable();
            $table->string('note')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // Payment rollup mirrored from the extended hr.payslip.
        Schema::table('payslips', function (Blueprint $table) {
            $table->string('payment_status', 16)->default('unpaid')->index()->after('net_total');
            $table->decimal('amount_paid', 14, 2)->default(0)->after('payment_status');
            $table->decimal('amount_due', 14, 2)->default(0)->after('amount_paid');
            $table->boolean('employee_confirmed')->default(false)->after('amount_due');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_payments');
        Schema::dropIfExists('salary_adjustments');
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'amount_paid', 'amount_due', 'employee_confirmed']);
        });
    }
};
