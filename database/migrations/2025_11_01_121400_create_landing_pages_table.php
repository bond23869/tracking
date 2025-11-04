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
            // query_hash removed - adds unnecessary complexity
            $table->text('full_url_sample')->nullable();
            // first_seen_at removed - use created_at instead
            $table->timestamps();

            $table->unique(['website_id', 'path'], 'landing_pages_site_path_unique');
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


