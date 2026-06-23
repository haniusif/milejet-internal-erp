<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Local cache of Odoo hr.loan (mj_loan addon) — same pattern as contracts.
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('odoo_employee_id')->index();
            $table->string('employee_name')->nullable();
            $table->date('date')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('installment', 12, 2)->default(0);
            $table->string('reason')->nullable();
            $table->string('state', 16)->default('draft')->index();
            $table->decimal('repaid_amount', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('loan_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->unsignedBigInteger('odoo_loan_id')->index();
            $table->date('date')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('note')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_lines');
        Schema::dropIfExists('loans');
    }
};
