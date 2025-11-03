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
        Schema::create('sessions_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->foreignId('landing_page_id')->nullable()->constrained('landing_pages')->nullOnDelete();
            $table->foreignId('referrer_domain_id')->nullable()->constrained('referrer_domains')->nullOnDelete();

            $table->text('landing_url')->nullable();
            $table->text('referrer_url')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->boolean('is_bot')->default(false);
            $table->boolean('is_bounced')->default(false);
            $table->timestamps();

            $table->index(['website_id', 'started_at']);
            $table->index(['customer_id', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions_tracking');
    }
};


