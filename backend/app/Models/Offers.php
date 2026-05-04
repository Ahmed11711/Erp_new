<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Offers extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'offer',
        'quote',
        'dateFrom',
        'dateTo',
        'subtotal',
        'vat',
        'total',
        'phone_number',
        'email',
        'title',
        'note',
        'transportation',
    ];

    public function category()
    {
        return $this->hasMany(OffersCategory::class, 'offer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}
