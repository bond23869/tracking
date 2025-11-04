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
        Schema::create('trackable_utm_values', function (Blueprint $table) {
            $table->id();
            $table->morphs('trackable'); // trackable_type, trackable_id (session/event/touch)
            $table->foreignId('custom_utm_value_id')->constrained('custom_utm_values')->onDelete('cascade');
            $table->timestamps();

            // Prevent duplicate assignments
            // Note: morphs('trackable') already creates an index on (trackable_type, trackable_id)
            $table->unique(['trackable_type', 'trackable_id', 'custom_utm_value_id'], 'trackable_utm_unique');
            $table->index(['custom_utm_value_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trackable_utm_values');
    }
};

