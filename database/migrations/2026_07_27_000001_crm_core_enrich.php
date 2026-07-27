<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// mj_crm_core: additive cache columns + tag/lost-reason cache tables.
// Surfaces existing Odoo crm data richer; no Odoo schema change.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->text('description')->nullable()->after('lost_reason');
            $table->unsignedBigInteger('odoo_user_id')->nullable()->after('salesperson_name'); // salesperson
            $table->unsignedBigInteger('odoo_lost_reason_id')->nullable()->after('lost_reason');
            $table->unsignedBigInteger('odoo_team_id')->nullable()->after('odoo_user_id');
            $table->string('team_name')->nullable()->after('odoo_team_id');
            $table->string('tag_names')->nullable()->after('team_name'); // comma-joined
        });

        Schema::table('crm_customers', function (Blueprint $table) {
            $table->unsignedBigInteger('odoo_user_id')->nullable()->after('vat'); // account manager
            $table->string('account_manager')->nullable()->after('odoo_user_id');
            $table->decimal('credit_limit', 14, 2)->nullable()->after('account_manager');
        });

        Schema::create('crm_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name');
            $table->unsignedInteger('color')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_lost_reasons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_lost_reasons');
        Schema::dropIfExists('crm_tags');
        Schema::table('crm_customers', function (Blueprint $table) {
            $table->dropColumn(['odoo_user_id', 'account_manager', 'credit_limit']);
        });
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropColumn(['description', 'odoo_user_id', 'odoo_lost_reason_id', 'odoo_team_id', 'team_name', 'tag_names']);
        });
    }
};
