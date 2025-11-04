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
        Schema::create('conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('session_id')->nullable()->constrained('tracking_sessions')->nullOnDelete();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');

            $table->timestamp('occurred_at');
            $table->integer('value_cents')->nullable();
            $table->string('currency', 3)->nullable();

            $table->foreignId('first_touch_id')->nullable()->constrained('touches')->nullOnDelete();
            $table->foreignId('last_non_direct_touch_id')->nullable()->constrained('touches')->nullOnDelete();
            $table->foreignId('attributed_touch_id')->nullable()->constrained('touches')->nullOnDelete();
            $table->string('attribution_model')->nullable(); // first_touch, last_non_direct, etc.
            $table->json('attribution_weight')->nullable(); // for multi-touch models later

            // UTM attribution fields
            $table->json('utm_current')->nullable(); // UTM at conversion time
            $table->json('utm_attribution')->nullable(); // Calculated attribution result

            // Order fields
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('order_number')->nullable();

            $table->timestamps();

            $table->index('order_id');
            $table->index('order_number');

            $table->index(['website_id', 'occurred_at']);
            $table->index(['customer_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversions');
    }
};


