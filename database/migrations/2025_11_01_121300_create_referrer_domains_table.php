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
        Schema::create('referrer_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->onDelete('cascade');
            $table->string('domain');
            $table->string('category')->nullable(); // search, social, email, direct, other
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['website_id', 'domain']);
            $table->index(['website_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrer_domains');
    }
};


