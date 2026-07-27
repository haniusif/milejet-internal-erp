<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// mj_fleet_kpi: cache of odometer readings (for distance / cost-per-km).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_odometer_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->unsignedBigInteger('odoo_vehicle_id')->index();
            $table->decimal('value', 12, 2)->default(0);
            $table->date('date')->nullable()->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_odometer_logs');
    }
};
