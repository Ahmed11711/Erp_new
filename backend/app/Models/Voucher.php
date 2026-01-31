<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'type',
        'voucher_type',
        'account_id',
        'client_or_supplier_name',
        'client_id',
        'supplier_id',
        'amount',
        'notes',
        'reference_number',
        'user_id',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function account()
    {
        return $this->belongsTo(TreeAccount::class, 'account_id');
    }

    public function client()
    {
        return $this->belongsTo(customerCompany::class, 'client_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

