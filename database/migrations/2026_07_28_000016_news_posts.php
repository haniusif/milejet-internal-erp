<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Company news / announcements (Laravel-only module; no Odoo business logic).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('category', 20)->default('announcement')->index(); // announcement | policy | event | general
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('pinned')->default(false);
            $table->boolean('published')->default(true)->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_posts');
    }
};
