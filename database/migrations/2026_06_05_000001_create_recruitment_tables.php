<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_positions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name');
            $table->unsignedBigInteger('odoo_department_id')->nullable();
            $table->string('department_name')->nullable();
            $table->unsignedInteger('no_of_recruitment')->default(0);
            $table->unsignedInteger('application_count')->default(0);
            $table->string('recruiter_name')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('recruitment_stages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name');
            $table->unsignedInteger('sequence')->default(0);
            $table->boolean('hired_stage')->default(false);
            $table->boolean('fold')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('partner_name');
            $table->string('email_from')->nullable();
            $table->string('partner_phone')->nullable();
            $table->string('partner_mobile')->nullable();
            $table->unsignedBigInteger('odoo_job_id')->nullable();
            $table->string('job_name')->nullable();
            $table->unsignedBigInteger('odoo_stage_id')->nullable();
            $table->string('stage_name')->nullable();
            $table->unsignedBigInteger('odoo_department_id')->nullable();
            $table->string('department_name')->nullable();
            $table->decimal('salary_expected', 12, 2)->nullable();
            $table->decimal('salary_proposed', 12, 2)->nullable();
            $table->date('availability')->nullable();
            $table->string('priority')->nullable();
            $table->string('kanban_state')->nullable();
            $table->string('refuse_reason')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('odoo_create_date')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicants');
        Schema::dropIfExists('recruitment_stages');
        Schema::dropIfExists('job_positions');
    }
};
