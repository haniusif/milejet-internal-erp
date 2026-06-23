<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Metadata caches of the mj_hr_forms Odoo models. PDFs render in Odoo.
        Schema::create('hr_warnings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->unsignedBigInteger('odoo_employee_id')->index();
            $table->string('employee_name')->nullable();
            $table->string('name')->nullable();
            $table->string('warning_type', 16)->index();
            $table->date('date')->nullable();
            $table->string('subject')->nullable();
            $table->text('description')->nullable();
            $table->string('state', 16)->default('draft')->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('hr_sick_leaves', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->unsignedBigInteger('odoo_employee_id')->index();
            $table->string('employee_name')->nullable();
            $table->string('name')->nullable();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->integer('days')->default(0);
            $table->string('diagnosis')->nullable();
            $table->string('doctor_name')->nullable();
            $table->string('facility')->nullable();
            $table->text('note')->nullable();
            $table->string('state', 16)->default('draft')->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('hr_service_ends', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->unsignedBigInteger('odoo_employee_id')->index();
            $table->string('employee_name')->nullable();
            $table->string('name')->nullable();
            $table->string('reason', 24)->index();
            $table->date('last_working_day')->nullable();
            $table->boolean('notice_served')->default(false);
            $table->boolean('custody_returned')->default(false);
            $table->text('custody_note')->nullable();
            $table->decimal('settlement_amount', 14, 2)->default(0);
            $table->text('clearance_note')->nullable();
            $table->string('state', 16)->default('draft')->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_warnings');
        Schema::dropIfExists('hr_sick_leaves');
        Schema::dropIfExists('hr_service_ends');
    }
};
