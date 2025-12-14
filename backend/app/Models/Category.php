<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;
    protected  $fillable = [
        'category_name',
        'category_price',
        'initial_balance',
        'quantity',
        'minimum_quantity',
        'warehouse',
        'production_id',
        'measurement_id',
        'category_image',
        'ref',
        'status',
        'stock_id',
    ];
    public function production()
    {
        return $this->belongsTo(Production::class);
    }
    public function measurement()
    {
        return $this->belongsTo(Measurement::class);
    }
    public function stock()
    {
        return $this->belongsTo(stock::class);
    }
}
