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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->onDelete('cascade');
            $table->foreignId('session_id')->nullable()->constrained('sessions_tracking')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->string('name');
            $table->timestamp('occurred_at');
            $table->json('props')->nullable();
            $table->integer('revenue_cents')->nullable();
            $table->string('currency', 3)->nullable();

            $table->string('idempotency_key')->unique();
            $table->foreignId('ingestion_token_id')->nullable()->constrained('ingestion_tokens')->nullOnDelete();
            $table->unsignedInteger('schema_version')->default(1);
            $table->string('sdk_version')->nullable();

            // Optional UTM/landing snapshots for faster joins
            $table->foreignId('utm_source_id')->nullable()->constrained('utm_sources')->nullOnDelete();
            $table->foreignId('utm_medium_id')->nullable()->constrained('utm_mediums')->nullOnDelete();
            $table->foreignId('utm_campaign_id')->nullable()->constrained('utm_campaigns')->nullOnDelete();
            $table->foreignId('utm_term_id')->nullable()->constrained('utm_terms')->nullOnDelete();
            $table->foreignId('utm_content_id')->nullable()->constrained('utm_contents')->nullOnDelete();
            $table->foreignId('referrer_domain_id')->nullable()->constrained('referrer_domains')->nullOnDelete();
            $table->foreignId('landing_page_id')->nullable()->constrained('landing_pages')->nullOnDelete();

            $table->timestamps();

            $table->index(['website_id', 'occurred_at']);
            $table->index(['session_id', 'occurred_at']);
            $table->index(['customer_id', 'occurred_at']);
            $table->index(['name', 'website_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};


