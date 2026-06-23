<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New vehicle fields from the OCA addons.
        Schema::table('fleet_vehicles', function (Blueprint $table) {
            $table->unsignedBigInteger('odoo_category_id')->nullable()->after('odoo_state_id');
            $table->string('category_name')->nullable()->after('odoo_category_id');
            $table->decimal('fuel_capacity', 8, 2)->nullable()->after('fuel_type');
        });

        // Vehicle categories (fleet_vehicle_category).
        Schema::create('fleet_vehicle_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // Vehicle inspections (fleet_vehicle_inspection).
        Schema::create('fleet_inspections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->unsignedBigInteger('odoo_vehicle_id')->index();
            $table->string('vehicle_name')->nullable();
            $table->string('name')->nullable();
            $table->string('state', 16)->default('draft')->index();
            $table->string('direction', 8)->nullable();
            $table->dateTime('date_inspected')->nullable();
            $table->decimal('odometer', 12, 2)->nullable();
            $table->string('odometer_unit', 16)->nullable();
            $table->string('inspected_by_name')->nullable();
            $table->string('result', 16)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_inspections');
        Schema::dropIfExists('fleet_vehicle_categories');
        Schema::table('fleet_vehicles', function (Blueprint $table) {
            $table->dropColumn(['odoo_category_id', 'category_name', 'fuel_capacity']);
        });
    }
};
