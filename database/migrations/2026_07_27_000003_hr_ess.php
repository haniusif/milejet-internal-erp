<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// mj_hr_ess cache: employee self-service requests.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('odoo_employee_id')->index();
            $table->string('employee_name')->nullable();
            $table->string('request_type', 16)->index();
            $table->string('state', 16)->default('draft')->index();
            $table->date('date_request')->nullable();
            $table->string('summary')->nullable();
            $table->text('description')->nullable();
            $table->string('approver_name')->nullable();
            $table->text('manager_note')->nullable();
            $table->string('certificate_kind', 16)->nullable();
            $table->string('addressed_to')->nullable();
            $table->boolean('has_certificate')->default(false);
            $table->string('target_department')->nullable();
            $table->date('last_working_day')->nullable();
            $table->string('resign_reason')->nullable();
            $table->string('item')->nullable();
            $table->integer('qty')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_requests');
    }
};
