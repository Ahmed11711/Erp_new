<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderProductArchive extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $guarded=[];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
