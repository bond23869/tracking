<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('landing_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->onDelete('cascade');
            $table->string('path');
            $table->string('query_hash', 64)->default('');
            $table->text('full_url_sample')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['website_id', 'path', 'query_hash'], 'landing_pages_site_path_query_unique');
            $table->index(['website_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landing_pages');
    }
};


