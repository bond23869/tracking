<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngestionToken extends Model
{
    use HasFactory;

    /**
     * The attributes that aren't mass assignable.
     * Using guarded to allow explicit control elsewhere.
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'scopes' => 'array',
        'ip_allowlist' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * Get the website this token belongs to.
     */
    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}


