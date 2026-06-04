<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('work_locations', function (Blueprint $table) {
            $table->id();
            // Synced from Odoo hr.work.location
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name');
            $table->string('location_type')->nullable();   // office | home | other
            $table->string('address_name')->nullable();    // address_id display name
            $table->boolean('active')->default(true);
            // Geofence — managed in Laravel only (Odoo CE has no coordinates on
            // hr.work.location); sync must never overwrite these columns.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('geofence_radius')->nullable(); // null → global default
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('odoo_work_location_id')->nullable()->after('department_name');
            $table->string('work_location_name')->nullable()->after('odoo_work_location_id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['odoo_work_location_id', 'work_location_name']);
        });
        Schema::dropIfExists('work_locations');
    }
};
