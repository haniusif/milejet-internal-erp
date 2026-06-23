<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Inspection checklist templates (fleet_vehicle_inspection_template).
        Schema::create('fleet_inspection_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // Inspection items (fleet.vehicle.inspection.item).
        Schema::create('fleet_inspection_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name');
            $table->text('instruction')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // Inspection checklist lines (fleet.vehicle.inspection.line).
        Schema::create('fleet_inspection_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->unsignedBigInteger('odoo_inspection_id')->index();
            $table->unsignedBigInteger('odoo_item_id')->nullable();
            $table->string('item_name')->nullable();
            $table->string('result', 16)->default('todo');
            $table->string('result_description')->nullable();
            $table->integer('sequence')->default(10);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // Vehicle usage / checkout log (fleet_vehicle_usage).
        Schema::create('fleet_vehicle_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('odoo_vehicle_id')->index();
            $table->string('vehicle_name')->nullable();
            $table->string('partner_name')->nullable();
            $table->string('state', 16)->default('draft')->index();
            $table->dateTime('date_picking')->nullable();
            $table->dateTime('date_return')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::table('fleet_vehicles', function (Blueprint $table) {
            $table->boolean('in_use')->default(false)->after('active');
        });

        Schema::table('fleet_service_logs', function (Blueprint $table) {
            $table->string('included_services')->nullable()->after('service_type_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_inspection_templates');
        Schema::dropIfExists('fleet_inspection_items');
        Schema::dropIfExists('fleet_inspection_lines');
        Schema::dropIfExists('fleet_vehicle_usages');
        Schema::table('fleet_vehicles', fn (Blueprint $t) => $t->dropColumn('in_use'));
        Schema::table('fleet_service_logs', fn (Blueprint $t) => $t->dropColumn('included_services'));
    }
};
