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

}
