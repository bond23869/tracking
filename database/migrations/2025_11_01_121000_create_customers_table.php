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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->onDelete('cascade');
            // first_seen_at/last_seen_at removed - use created_at/updated_at instead
            $table->unsignedBigInteger('first_touch_id')->nullable();
            $table->unsignedBigInteger('last_touch_id')->nullable();
            $table->string('email_hash')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['website_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};


