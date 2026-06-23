<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Local cache of Odoo res.company — branches are companies with a parent.
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name');
            $table->unsignedBigInteger('odoo_parent_id')->nullable()->index();
            $table->string('parent_name')->nullable();
            $table->string('company_registry')->nullable(); // CR number
            $table->string('vat')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('city')->nullable();
            $table->string('country_name')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('odoo_company_id')->nullable()->index()->after('odoo_department_id');
            $table->string('company_name')->nullable()->after('odoo_company_id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['odoo_company_id', 'company_name']);
        });
        Schema::dropIfExists('companies');
    }
};
