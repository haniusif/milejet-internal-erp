<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // الأقسام
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->integer('odoo_id')->unique();
            $table->string('name');
            $table->integer('odoo_parent_id')->nullable();
            $table->string('parent_name')->nullable();
            $table->integer('odoo_manager_id')->nullable();
            $table->string('manager_name')->nullable();
            $table->integer('total_employee')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // الموظفين
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->integer('odoo_id')->unique();
            $table->string('name');
            $table->string('job_title')->nullable();
            $table->string('work_email')->nullable();
            $table->string('work_phone')->nullable();
            $table->string('mobile_phone')->nullable();
            $table->integer('odoo_department_id')->nullable()->index();
            $table->string('department_name')->nullable();
            $table->integer('odoo_parent_id')->nullable(); // المدير المباشر
            $table->string('parent_name')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // أنواع الإجازات (lookup table)
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->integer('odoo_id')->unique();
            $table->string('name');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // طلبات الإجازة
        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->integer('odoo_id')->unique();
            $table->integer('odoo_employee_id')->index();
            $table->string('employee_name');
            $table->integer('odoo_leave_type_id')->nullable();
            $table->string('leave_type_name')->nullable();
            $table->dateTime('date_from');
            $table->dateTime('date_to');
            $table->decimal('number_of_days', 5, 2)->default(0);
            $table->string('state')->default('draft'); // draft/confirm/validate/refuse/cancel
            $table->text('description')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // الحضور
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->integer('odoo_id')->unique();
            $table->integer('odoo_employee_id')->index();
            $table->string('employee_name');
            $table->dateTime('check_in');
            $table->dateTime('check_out')->nullable();
            $table->decimal('worked_hours', 5, 2)->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // سجل المزامنة
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('model'); // hr.employee, hr.department, ...
            $table->integer('records_synced')->default(0);
            $table->integer('records_failed')->default(0);
            $table->text('error_message')->nullable();
            $table->string('status'); // success/failed/partial
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('leaves');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('departments');
    }
};
