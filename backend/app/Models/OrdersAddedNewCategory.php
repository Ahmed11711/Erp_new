<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrdersAddedNewCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_name',
        'old_price',
        'new_price',
        'tracking_id',
    ];

    public function tracking()
    {
        return $this->belongsTo(tracking::class);
    }


}
