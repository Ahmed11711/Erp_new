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
        'client_kind',
        'individual_customer_phone',
        'individual_customer_name',
        'supplier_id',
        'shipping_company_id',
        'collection_company_id',
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

    public function shippingCompany()
    {
        return $this->belongsTo(ShippingCompany::class, 'shipping_company_id');
    }

    public function collectionCompany()
    {
        return $this->belongsTo(CollectionCompany::class, 'collection_company_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function accountEntries()
    {
        return $this->hasMany(AccountEntry::class);
    }
}

