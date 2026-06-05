<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_vehicle_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name');
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fleet_vehicle_models', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name');               // "Toyota/Corolla"
            $table->string('brand_name')->nullable();
            $table->string('vehicle_type')->nullable(); // car | bike
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fleet_service_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fleet_vehicles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name');               // display name (model + plate)
            $table->unsignedBigInteger('odoo_model_id')->nullable();
            $table->string('model_name')->nullable();
            $table->string('license_plate')->nullable();
            $table->string('vin_sn')->nullable();
            $table->unsignedBigInteger('odoo_driver_partner_id')->nullable();
            $table->string('driver_name')->nullable();
            $table->unsignedBigInteger('odoo_state_id')->nullable();
            $table->string('state_name')->nullable();
            $table->decimal('odometer', 12, 2)->default(0);
            $table->string('odometer_unit')->nullable();
            $table->string('fuel_type')->nullable();
            $table->string('model_year')->nullable();
            $table->string('color')->nullable();
            $table->unsignedInteger('seats')->nullable();
            $table->unsignedInteger('doors')->nullable();
            $table->date('acquisition_date')->nullable();
            $table->decimal('car_value', 14, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fleet_service_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->unsignedBigInteger('odoo_vehicle_id');
            $table->string('vehicle_name')->nullable();
            $table->string('description')->nullable();
            $table->string('service_type_name')->nullable();
            $table->date('date')->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('vendor_name')->nullable();
            $table->string('state')->nullable(); // todo | running | done | cancelled
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_service_logs');
        Schema::dropIfExists('fleet_vehicles');
        Schema::dropIfExists('fleet_service_types');
        Schema::dropIfExists('fleet_vehicle_models');
        Schema::dropIfExists('fleet_vehicle_states');
    }
};
