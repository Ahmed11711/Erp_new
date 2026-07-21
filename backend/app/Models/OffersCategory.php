<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OffersCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'offer_id',
        'category_name',
        'matched_category_id',
        'description',
        'category_quantity',
        'old_category_price',
        'new_category_price',
        'category_image',
        'total_price'
    ];

    public function offers()
    {
        return $this->belongsTo(Offers::class);
    }

    public function matchedCategory()
    {
        return $this->belongsTo(Category::class, 'matched_category_id');
    }
}
