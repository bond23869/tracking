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
        Schema::create('custom_utm_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_utm_parameter_id')->constrained('custom_utm_parameters')->onDelete('cascade');
            $table->foreignId('website_id')->constrained()->onDelete('cascade');
            $table->string('value');
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['custom_utm_parameter_id', 'website_id', 'value'], 'custom_utm_values_unique');
            $table->index(['website_id', 'custom_utm_parameter_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_utm_values');
    }
};
