<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionCompany extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'status',
        'notes',
        'linked_shipping_company_id',
        'receivable_tree_account_id',
    ];

    public function linkedShippingCompany(): BelongsTo
    {
        return $this->belongsTo(ShippingCompany::class, 'linked_shipping_company_id');
    }

    public function receivableTreeAccount(): BelongsTo
    {
        return $this->belongsTo(TreeAccount::class, 'receivable_tree_account_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
