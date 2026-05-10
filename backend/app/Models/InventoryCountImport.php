<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCountImport extends Model
{
    protected $fillable = [
        'import_token',
        'filename',
        'status',
        'total_rows',
        'matched_rows',
        'new_items_rows',
        'skipped_rows',
        'adjusted_rows',
        'preview_data',
        'warnings',
        'summary',
        'user_id',
        'previewed_at',
        'confirmed_at',
        'expires_at',
    ];

    protected $casts = [
        'preview_data' => 'array',
        'warnings' => 'array',
        'summary' => 'array',
        'previewed_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function rows(): HasMany
    {
        return $this->hasMany(InventoryCountImportRow::class, 'import_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
