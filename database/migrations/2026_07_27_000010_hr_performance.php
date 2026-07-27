<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// mj_hr_performance cache: appraisals, objective/KPI lines, recognition.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_appraisals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('odoo_employee_id')->index();
            $table->string('employee_name')->nullable();
            $table->string('period')->nullable();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->string('reviewer_name')->nullable();
            $table->string('state', 20)->default('draft')->index();
            $table->float('overall_rating')->default(0);
            $table->text('summary')->nullable();
            $table->unsignedBigInteger('reward_odoo_id')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('hr_appraisal_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->unsignedBigInteger('odoo_appraisal_id')->index();
            $table->integer('sequence')->default(10);
            $table->string('name');
            $table->string('category', 16)->default('objective');
            $table->string('skill_name')->nullable();
            $table->float('weight')->default(0);
            $table->string('target')->nullable();
            $table->float('auto_value')->default(0);
            $table->boolean('is_auto')->default(false);
            $table->string('self_rating', 2)->nullable();
            $table->string('manager_rating', 2)->nullable();
            $table->float('score')->default(0);
            $table->timestamps();
        });

        Schema::create('hr_recognitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->unsignedBigInteger('odoo_employee_id')->index();
            $table->string('employee_name')->nullable();
            $table->string('from_name')->nullable();
            $table->string('badge', 16)->default('kudos');
            $table->string('message');
            $table->timestamp('date')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_recognitions');
        Schema::dropIfExists('hr_appraisal_lines');
        Schema::dropIfExists('hr_appraisals');
    }
};
