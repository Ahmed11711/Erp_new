<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManufactureAddition extends Model
{
    protected $fillable = [
        'name',
        'cost',
        'unit',
    ];

    protected $casts = [
        'cost' => 'decimal:4',
    ];
}
