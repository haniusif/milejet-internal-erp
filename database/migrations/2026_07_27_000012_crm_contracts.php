<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// mj_crm_contract cache: customer service contracts + rate-card lines.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('odoo_partner_id')->index();
            $table->string('partner_name')->nullable();
            $table->unsignedBigInteger('odoo_lead_id')->nullable();
            $table->unsignedBigInteger('odoo_user_id')->nullable()->index();
            $table->string('user_name')->nullable();
            $table->date('date_start')->nullable();
            $table->date('date_end')->nullable();
            $table->string('recurrence', 12)->default('monthly');
            $table->boolean('auto_renew')->default(false);
            $table->string('payment_term')->nullable();
            $table->string('currency', 8)->nullable();
            $table->decimal('amount_recurring', 14, 2)->default(0);
            $table->date('next_invoice_date')->nullable();
            $table->string('state', 12)->default('draft')->index();
            $table->integer('delivery_sla_hours')->nullable();
            $table->float('on_time_target')->nullable();
            $table->integer('invoice_count')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_contract_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->unsignedBigInteger('odoo_contract_id')->index();
            $table->integer('sequence')->default(10);
            $table->unsignedBigInteger('odoo_product_id')->nullable();
            $table->string('product_name')->nullable();
            $table->string('name')->nullable();
            $table->string('basis', 12)->default('fixed');
            $table->float('quantity')->default(1);
            $table->decimal('price_unit', 14, 2)->default(0);
            $table->decimal('price_subtotal', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_contract_lines');
        Schema::dropIfExists('crm_contracts');
    }
};
