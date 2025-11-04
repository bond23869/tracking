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
        Schema::create('identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->onDelete('cascade');
            $table->string('type'); // cookie, user_id, email_hash, ga_cid, etc.
            $table->string('value_hash');
            // first_seen_at/last_seen_at removed - use created_at/updated_at instead
            $table->timestamps();

            $table->unique(['website_id', 'type', 'value_hash'], 'identities_site_type_value_unique');
            $table->index(['website_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('identities');
    }
};


