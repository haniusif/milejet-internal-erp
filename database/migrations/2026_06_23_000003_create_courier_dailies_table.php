<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cache of Odoo mj.courier.daily (mj_courier_daily addon).
        Schema::create('courier_dailies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->date('date')->index();
            $table->string('month', 7)->index();
            $table->unsignedBigInteger('odoo_employee_id')->nullable()->index();
            $table->string('courier_name')->nullable();
            $table->string('vehicle_plate')->nullable();
            $table->string('city', 32)->nullable();
            $table->string('project', 32)->nullable();
            $table->boolean('present')->default(false);
            $table->integer('ofd')->default(0);
            $table->integer('delivered')->default(0);
            $table->decimal('performance', 6, 2)->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_dailies');
    }
};
