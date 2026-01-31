<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'entry_number',
        'description',
        'user_id',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(DailyEntryItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

