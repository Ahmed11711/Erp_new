<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable=[
        'supplier_name',
        'supplier_phone',
        'supplier_address',
        'supplier_type',
        'supplier_rate',
        'price_rate',
        'balance',
        'last_balance',
    ];
    public function supplierType(){
        return $this->belongsTo(SupplierType::class, 'supplier_type');
    }

    public function purchases(){
        return $this->hasMany(Purchase::class, 'supplier_id');
    }
}
