<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->boolean('signed')->default(false)->after('struct_name');
            $table->timestamp('signed_date')->nullable()->after('signed');
            $table->string('signed_by')->nullable()->after('signed_date');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['signed', 'signed_date', 'signed_by']);
        });
    }
};
