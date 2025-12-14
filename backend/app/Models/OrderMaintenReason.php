<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderMaintenReason extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'order_status',
        'mainten_reason',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

}
