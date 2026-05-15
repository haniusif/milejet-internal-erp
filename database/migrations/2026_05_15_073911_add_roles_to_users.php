<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('odoo_group_ids')->nullable()->after('odoo_api_key');
            $table->json('roles')->nullable()->after('odoo_group_ids');
            $table->timestamp('roles_synced_at')->nullable()->after('roles');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['odoo_group_ids', 'roles', 'roles_synced_at']);
        });
    }
};
