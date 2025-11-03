<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsitePixel extends Model
{
    protected $fillable = [
        'website_id',
        'platform',
        'name',
        'is_active',
        'pixel_id',
        'access_token',
        'conversion_id',
        'conversion_labels',
        'tag_id',
        'ad_account_id',
        'snapchat_pixel_id',
        'event_ids',
        'public_api_key',
        'private_api_key',
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'conversion_labels' => 'array',
        'event_ids' => 'array',
    ];

    public function website()
    {
        return $this->belongsTo(Website::class);
    }

    public function getPixelUrlAttribute()
    {
        return "https://www.facebook.com/tr?id={$this->pixel_id}&ev=PageView&noscript=1";
    }
}
