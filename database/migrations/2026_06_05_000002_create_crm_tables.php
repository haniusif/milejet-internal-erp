<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_stages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name');
            $table->unsignedInteger('sequence')->default(0);
            $table->boolean('is_won')->default(false);
            $table->boolean('fold')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name');                       // opportunity title
            $table->string('type')->default('opportunity'); // lead | opportunity
            $table->string('contact_name')->nullable();
            $table->string('partner_name')->nullable();   // company
            $table->unsignedBigInteger('odoo_partner_id')->nullable();
            $table->string('email_from')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->decimal('expected_revenue', 14, 2)->nullable();
            $table->decimal('probability', 5, 2)->nullable();
            $table->unsignedBigInteger('odoo_stage_id')->nullable();
            $table->string('stage_name')->nullable();
            $table->string('salesperson_name')->nullable();
            $table->date('date_deadline')->nullable();
            $table->string('priority')->nullable();
            $table->string('lost_reason')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('odoo_create_date')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name');
            $table->boolean('is_company')->default(false);
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('city')->nullable();
            $table->string('country_name')->nullable();
            $table->string('vat')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_customers');
        Schema::dropIfExists('crm_leads');
        Schema::dropIfExists('crm_stages');
    }
};
