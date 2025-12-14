<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tracking extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'action',
        'date',
        'user_id',
        'orders_added_new_categories_id',
    ];

    public function order(){
        return $this->belongsTo(Order::class);
    }
    public function user(){
        return $this->belongsTo(User::class);
    }
    public function editCategory()
    {
        return $this->hasMany(OrdersAddedNewCategory::class);
    }
}
