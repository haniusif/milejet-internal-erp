<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('emp_code', 32)->nullable()->index()->after('odoo_id');
            $table->date('date_of_joining')->nullable()->after('parent_name');
            $table->date('contract_end_date')->nullable()->after('date_of_joining');
            $table->string('contract_status', 32)->nullable()->after('contract_end_date');
            $table->date('birthday')->nullable()->after('contract_status');
            $table->string('family_status', 8)->nullable()->after('birthday');
            $table->string('cchi_card_type', 16)->nullable()->after('family_status');
            $table->string('nationality_code', 16)->nullable()->after('cchi_card_type');
            $table->string('nationality', 64)->nullable()->after('nationality_code');
            $table->string('region', 64)->nullable()->after('nationality');
            $table->string('passport_id', 64)->nullable()->after('region');
            $table->string('iqama_id', 64)->nullable()->after('passport_id');
            $table->string('status_label', 32)->nullable()->after('iqama_id');
            $table->string('contract_type', 32)->nullable()->after('status_label');

            $table->decimal('total_salary', 12, 2)->nullable()->after('contract_type');
            $table->decimal('basic_salary', 12, 2)->nullable()->after('total_salary');
            $table->decimal('allowance_house', 12, 2)->nullable()->after('basic_salary');
            $table->decimal('allowance_rent', 12, 2)->nullable()->after('allowance_house');
            $table->decimal('allowance_transport', 12, 2)->nullable()->after('allowance_rent');
            $table->decimal('allowance_car', 12, 2)->nullable()->after('allowance_transport');
            $table->decimal('allowance_special', 12, 2)->nullable()->after('allowance_car');
            $table->decimal('allowance_project', 12, 2)->nullable()->after('allowance_special');
            $table->decimal('allowance_food', 12, 2)->nullable()->after('allowance_project');
            $table->decimal('allowance_other', 12, 2)->nullable()->after('allowance_food');
            $table->decimal('ot_allowance', 12, 2)->nullable()->after('allowance_other');
            $table->decimal('loan_balance', 12, 2)->nullable()->after('ot_allowance');
            $table->decimal('alt_ticket', 12, 2)->nullable()->after('loan_balance');
            $table->decimal('bonus_eligibility_months', 8, 2)->nullable()->after('alt_ticket');
            $table->decimal('bonus_pm', 12, 2)->nullable()->after('bonus_eligibility_months');
            $table->decimal('gosi_pm', 12, 2)->nullable()->after('bonus_pm');
            $table->decimal('indemnity_pm', 12, 2)->nullable()->after('gosi_pm');
            $table->decimal('leave_accrual_pm', 12, 2)->nullable()->after('indemnity_pm');
            $table->decimal('med_insurance_pm', 12, 2)->nullable()->after('leave_accrual_pm');
            $table->decimal('pa_insurance_pm', 12, 2)->nullable()->after('med_insurance_pm');

            $table->timestamp('master_imported_at')->nullable()->after('synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'emp_code', 'date_of_joining', 'contract_end_date', 'contract_status',
                'birthday', 'family_status', 'cchi_card_type', 'nationality_code',
                'nationality', 'region', 'passport_id', 'iqama_id', 'status_label',
                'contract_type', 'total_salary', 'basic_salary', 'allowance_house',
                'allowance_rent', 'allowance_transport', 'allowance_car',
                'allowance_special', 'allowance_project', 'allowance_food',
                'allowance_other', 'ot_allowance', 'loan_balance', 'alt_ticket',
                'bonus_eligibility_months', 'bonus_pm', 'gosi_pm', 'indemnity_pm',
                'leave_accrual_pm', 'med_insurance_pm', 'pa_insurance_pm',
                'master_imported_at',
            ]);
        });
    }
};
