<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// mj_hr_training cache: courses, sessions, enrollments, training needs.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_courses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('category', 20)->default('skills')->index();
            $table->text('description')->nullable();
            $table->float('duration_hours')->default(1);
            $table->boolean('is_mandatory')->default(false);
            $table->integer('validity_months')->default(0);
            $table->integer('pass_mark')->default(0);
            $table->text('skill_names')->nullable();
            $table->integer('session_count')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('odoo_course_id')->index();
            $table->string('course_name')->nullable();
            $table->string('trainer_name')->nullable();
            $table->string('mode', 16)->default('in_person');
            $table->string('location')->nullable();
            $table->dateTime('date_start')->nullable();
            $table->dateTime('date_end')->nullable();
            $table->integer('capacity')->default(0);
            $table->integer('seats_taken')->default(0);
            $table->integer('seats_left')->default(0);
            $table->string('state', 16)->default('draft')->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('training_enrollments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->unsignedBigInteger('odoo_session_id')->index();
            $table->string('session_name')->nullable();
            $table->unsignedBigInteger('odoo_course_id')->nullable();
            $table->string('course_name')->nullable();
            $table->unsignedBigInteger('odoo_employee_id')->index();
            $table->string('employee_name')->nullable();
            $table->string('state', 16)->default('enrolled')->index();
            $table->integer('score')->nullable();
            $table->date('completion_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('has_certificate')->default(false);
            $table->string('feedback_rating', 2)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('training_needs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->unsignedBigInteger('odoo_employee_id')->index();
            $table->string('employee_name')->nullable();
            $table->string('skill_name')->nullable();
            $table->string('source', 16)->default('manual');
            $table->string('state', 16)->default('open')->index();
            $table->string('note')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_needs');
        Schema::dropIfExists('training_enrollments');
        Schema::dropIfExists('training_sessions');
        Schema::dropIfExists('training_courses');
    }
};
