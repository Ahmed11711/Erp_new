<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryMonthlyInventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'month', 'quantity',
        'total_price',
        'sell_total_price',
        'by'
    ];

    public function category(){
        return $this->belongsTo(Category::class,'category_id');
    }
}
