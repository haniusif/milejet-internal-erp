<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Per-employee geofence bypass: true = can punch from anywhere.
            $table->boolean('geofence_exempt')->default(false)->after('odoo_work_location_id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('geofence_exempt');
        });
    }
};
