<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManufactureProduct extends Model
{
    use HasFactory;
    protected $fillable = [
        'manufacture_id',
        'product_id',
        'quantity',
        'total_price',
    ];

    public function manufacture()
    {
        return $this->belongsTo(Manufacture::class, 'manufacture_id');
    }

    public function product()
    {
        return $this->belongsTo(Category::class, 'product_id');
    }
}
