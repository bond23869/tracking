<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsitePixel extends Model
{
    protected $fillable = ['website_id', 'pixel_id', 'access_token'];

    public function website()
    {
        return $this->belongsTo(Website::class);
    }

    public function getPixelUrlAttribute()
    {
        return "https://www.facebook.com/tr?id={$this->pixel_id}&ev=PageView&noscript=1";
    }
}
