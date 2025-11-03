<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Website extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'archived_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function ingestionTokens(): HasMany
    {
        return $this->hasMany(IngestionToken::class);
    }

    public function pixels(): HasMany
    {
        return $this->hasMany(WebsitePixel::class);
    }

    /**
     * Scope to exclude archived websites.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /**
     * Scope to only archived websites.
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    /**
     * Check if website is archived.
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Archive the website.
     */
    public function archive(): void
    {
        $this->update(['archived_at' => now()]);
    }

    /**
     * Unarchive the website.
     */
    public function unarchive(): void
    {
        $this->update(['archived_at' => null]);
    }
}
