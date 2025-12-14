<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchasesTracking extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'invoice_number',
        'action',
        'user_id',
    ];

    public function purchase(){
        return $this->belongsTo(Purchase::class);
    }
    public function user(){
        return $this->belongsTo(User::class);
    }
}
