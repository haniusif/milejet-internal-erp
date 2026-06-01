<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // GPS captured at check-in / check-out (mirrors Odoo hr.attendance fields)
            $table->decimal('in_latitude', 10, 7)->nullable()->after('check_in');
            $table->decimal('in_longitude', 10, 7)->nullable()->after('in_latitude');
            $table->decimal('out_latitude', 10, 7)->nullable()->after('check_out');
            $table->decimal('out_longitude', 10, 7)->nullable()->after('out_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['in_latitude', 'in_longitude', 'out_latitude', 'out_longitude']);
        });
    }
};
