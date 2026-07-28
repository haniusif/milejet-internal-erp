<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// mj_crm_helpdesk cache: support tickets with contract-driven SLA.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name')->nullable();
            $table->string('subject');
            $table->unsignedBigInteger('odoo_partner_id')->nullable()->index();
            $table->string('partner_name')->nullable();
            $table->unsignedBigInteger('odoo_contract_id')->nullable();
            $table->string('contract_name')->nullable();
            $table->text('description')->nullable();
            $table->string('category', 20)->default('other')->index();
            $table->string('priority', 10)->default('normal')->index();
            $table->string('source', 12)->nullable();
            $table->string('team_name')->nullable();
            $table->unsignedBigInteger('odoo_user_id')->nullable()->index();
            $table->string('user_name')->nullable();
            $table->string('stage', 16)->default('new')->index();
            $table->integer('sla_hours')->nullable();
            $table->dateTime('sla_deadline')->nullable();
            $table->string('sla_state', 12)->default('on_track')->index();
            $table->dateTime('date_open')->nullable();
            $table->dateTime('date_closed')->nullable();
            $table->text('resolution')->nullable();
            $table->string('satisfaction', 8)->nullable();
            $table->string('related_ref')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_tickets');
    }
};
