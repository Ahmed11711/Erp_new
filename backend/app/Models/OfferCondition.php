<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfferCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'condition',
    ];
}
