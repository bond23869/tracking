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
        Schema::table('website_pixels', function (Blueprint $table) {
            $table->string('platform')->after('website_id'); // meta, google, tiktok, pinterest, snapchat, x, klaviyo, reddit
            $table->string('name')->after('platform');
            $table->boolean('is_active')->default(true)->after('name');
            
            // Make existing columns nullable as they're platform-specific
            $table->string('pixel_id')->nullable()->change();
            $table->string('access_token')->nullable()->change();
            
            // Add platform-specific columns (all nullable)
            $table->string('conversion_id')->nullable()->after('access_token'); // Google
            $table->json('conversion_labels')->nullable()->after('conversion_id'); // Google
            $table->string('tag_id')->nullable()->after('conversion_labels'); // Pinterest
            $table->string('ad_account_id')->nullable()->after('tag_id'); // Pinterest
            $table->string('snapchat_pixel_id')->nullable()->after('ad_account_id'); // Snapchat
            $table->json('event_ids')->nullable()->after('snapchat_pixel_id'); // X.com
            $table->string('public_api_key')->nullable()->after('event_ids'); // Klaviyo
            $table->string('private_api_key')->nullable()->after('public_api_key'); // Klaviyo
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('website_pixels', function (Blueprint $table) {
            $table->dropColumn([
                'platform',
                'name',
                'is_active',
                'conversion_id',
                'conversion_labels',
                'tag_id',
                'ad_account_id',
                'snapchat_pixel_id',
                'event_ids',
                'public_api_key',
                'private_api_key',
            ]);
            
            // Restore original not null constraint
            $table->string('pixel_id')->nullable(false)->change();
            $table->string('access_token')->nullable(false)->change();
        });
    }
};
