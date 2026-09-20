<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TransactionType extends Model
{
    protected $fillable = [
        'name',
        'code',
        'prefix',
        'affects_stock',
        'stock_direction',
        'is_active',
    ];

    protected $casts = [
        'affects_stock' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function sequences(): HasMany
    {
        return $this->hasMany(DocumentSequence::class);
    }

    public function sequence(): HasOne
    {
        return $this->hasOne(DocumentSequence::class);
    }

    public function stockTransactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class, 'transaction_type_id');
    }
}
