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
        Schema::create('session_custom_utm_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('sessions_tracking')->onDelete('cascade');
            $table->foreignId('custom_utm_value_id')->constrained('custom_utm_values')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['session_id', 'custom_utm_value_id']);
            $table->index(['session_id']);
            $table->index(['custom_utm_value_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_custom_utm_values');
    }
};
