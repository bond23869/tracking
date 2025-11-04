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
        Schema::create('touches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('session_id')->nullable()->constrained('tracking_sessions')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->string('type'); // landing, ad_click, email_open, etc.

            $table->foreignId('referrer_domain_id')->nullable()->constrained('referrer_domains')->nullOnDelete();
            $table->foreignId('landing_page_id')->nullable()->constrained('landing_pages')->nullOnDelete();

            $table->foreignId('source_event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['website_id', 'customer_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('touches');
    }
};


