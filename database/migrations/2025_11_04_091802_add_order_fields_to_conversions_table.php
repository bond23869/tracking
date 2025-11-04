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
        // Order fields are now in the main conversions table migration
        // This migration is kept for backwards compatibility but is now a no-op
        Schema::table('conversions', function (Blueprint $table) {
            // Fields already exist in base migration
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversions', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->dropIndex(['order_number']);
            $table->dropColumn(['order_id', 'order_number']);
        });
    }
};
