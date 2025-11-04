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
        Schema::create('customer_identity_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('identity_id')->constrained()->onDelete('cascade');
            $table->decimal('confidence', 5, 4)->unsigned()->default(1.0000); // 0..1
            $table->string('source')->nullable(); // login, heuristic, sdk
            // first_seen_at/last_seen_at removed - use created_at/updated_at instead
            $table->timestamps();

            $table->unique(['customer_id', 'identity_id']);
            $table->index(['identity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_identity_links');
    }
};


