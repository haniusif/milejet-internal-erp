<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('payroll_tables'); // remove the empty boilerplate table if it was created earlier

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->integer('odoo_id')->unique();
            $table->string('name');
            $table->integer('odoo_employee_id')->index();
            $table->string('employee_name');
            $table->decimal('wage', 12, 2)->default(0);
            $table->date('date_start')->nullable();
            $table->date('date_end')->nullable();
            $table->string('state', 32)->default('draft');
            $table->integer('odoo_struct_id')->nullable();
            $table->string('struct_name')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->integer('odoo_id')->unique();
            $table->string('number')->nullable();
            $table->integer('odoo_employee_id')->index();
            $table->string('employee_name');
            $table->integer('odoo_contract_id')->nullable();
            $table->date('date_from');
            $table->date('date_to');
            $table->string('state', 32)->default('draft');
            $table->decimal('basic_total', 14, 2)->default(0);
            $table->decimal('allowance_total', 14, 2)->default(0);
            $table->decimal('gross_total', 14, 2)->default(0);
            $table->decimal('deduction_total', 14, 2)->default(0);
            $table->decimal('net_total', 14, 2)->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payslip_lines', function (Blueprint $table) {
            $table->id();
            $table->integer('odoo_id')->unique();
            $table->integer('odoo_payslip_id')->index();
            $table->string('code', 64);
            $table->string('name');
            $table->string('category_code', 32)->nullable();
            $table->string('category_name')->nullable();
            $table->decimal('total', 14, 2)->default(0);
            $table->integer('sequence')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_lines');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('contracts');
    }
};
