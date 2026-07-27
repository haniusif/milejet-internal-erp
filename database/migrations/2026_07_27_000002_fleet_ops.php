<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// mj_fleet_ops cache: fuel logs, accidents; vehicle compliance cols.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_vehicles', function (Blueprint $table) {
            $table->date('inspection_expiry')->nullable()->after('in_use');
            $table->string('fuel_card_no')->nullable()->after('inspection_expiry');
        });

        Schema::create('fleet_fuel_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->unsignedBigInteger('odoo_vehicle_id')->index();
            $table->string('vehicle_name')->nullable();
            $table->string('driver_name')->nullable();
            $table->date('date')->nullable();
            $table->decimal('liters', 10, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('price_per_liter', 10, 3)->default(0);
            $table->decimal('odometer', 12, 2)->nullable();
            $table->string('state', 16)->default('todo');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fleet_accidents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('odoo_vehicle_id')->index();
            $table->string('vehicle_name')->nullable();
            $table->string('driver_name')->nullable();
            $table->dateTime('date')->nullable();
            $table->string('location')->nullable();
            $table->string('severity', 16)->default('minor')->index();
            $table->text('description')->nullable();
            $table->string('third_party')->nullable();
            $table->decimal('repair_cost', 12, 2)->default(0);
            $table->string('insurer')->nullable();
            $table->string('claim_state', 16)->default('none')->index();
            $table->decimal('claim_amount', 12, 2)->default(0);
            $table->string('state', 16)->default('draft')->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_fuel_logs');
        Schema::dropIfExists('fleet_accidents');
        Schema::table('fleet_vehicles', function (Blueprint $table) {
            $table->dropColumn(['inspection_expiry', 'fuel_card_no']);
        });
    }
};
