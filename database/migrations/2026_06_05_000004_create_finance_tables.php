<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer invoices + vendor bills (Odoo account.move). One table,
        // discriminated by move_type — mirrors how CRM keeps leads in one table.
        Schema::create('finance_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('move_type');                 // out_invoice | in_invoice | out_refund | in_refund
            $table->string('name')->nullable();          // INV/2026/00001
            $table->string('ref')->nullable();           // source/customer reference
            $table->unsignedBigInteger('odoo_partner_id')->nullable();
            $table->string('partner_name')->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('invoice_date_due')->nullable();
            $table->decimal('amount_total', 16, 2)->default(0);
            $table->decimal('amount_residual', 16, 2)->default(0);
            $table->string('currency')->nullable();
            $table->string('state')->nullable();         // draft | posted | cancel
            $table->string('payment_state')->nullable(); // not_paid | in_payment | paid | partial | reversed
            $table->string('journal_name')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('move_type');
            $table->index('state');
            $table->index('payment_state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_invoices');
    }
};
