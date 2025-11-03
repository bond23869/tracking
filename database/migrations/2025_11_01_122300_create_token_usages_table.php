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
        Schema::create('token_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingestion_token_id')->constrained('ingestion_tokens')->onDelete('cascade');
            $table->foreignId('website_id')->constrained()->onDelete('cascade');
            $table->timestamp('occurred_at');
            $table->string('ip', 45)->nullable();
            $table->boolean('success')->default(true);
            $table->string('error_code')->nullable();
            $table->string('request_id')->nullable();
            $table->timestamps();

            $table->index(['ingestion_token_id', 'occurred_at']);
            $table->index(['website_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('token_usages');
    }
};


