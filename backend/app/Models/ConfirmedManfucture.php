<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfirmedManfucture extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'quantity',
        'status',
        'total',
        'date',
        'user_id'
    ];
    public function product(){
        return $this->belongsTo(Category::class,'product_id');
    }
    public function user(){
        return $this->belongsTo(User::class,'user_id');
    }
    
}
