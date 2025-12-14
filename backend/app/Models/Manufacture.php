<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Manufacture extends Model
{
    use HasFactory;
    protected $fillable = [
        'product_id',
        'total', 
    ];

    public function product()
    {
        return $this->belongsTo(Category::class, 'product_id');
    }
    public function manufacture_products()
    {
        return $this->hasMany(ManufactureProduct::class, 'manufacture_id');
    }
}
