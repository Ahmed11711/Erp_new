<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    /**
     * Document numbers are assigned in PurchasesController using DocumentNumberService (PUR-xxxx).
     * Legacy PO auto-numbering was removed so sequences stay duplicate-safe under concurrency.
     */

    protected $fillable = [
        'invoice_number',
        'invoice_no',
        'external_invoice_no',
        'printable_status',
        'notes',
        'supplier_id',
        'supplierpay_id',
        'invoice_type',
        'receipt_date',
        'total_price',
        'paid_amount',
        'due_amount',
        'transport_cost',
        'product_total',
        'shipping_total',
        'grand_total',
        'invoice_image',
        'price_edited',
        'bank_id',
        'payment_type',
        'safe_id',
        'service_account_id',
        'status',
        'edits',
        'ref'
    ];

    public function supplier(){
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function tracking(){
        return $this->hasMany(PurchasesTracking::class);
    }

    public function updatedPurchase()
    {
        return $this->hasOne(Purchase::class, 'ref')->latest('id');
    }

    public function bank()
    {
        return $this->hasOne(Bank::class, 'id', 'bank_id');
    }

    public function safe()
    {
        return $this->belongsTo(Safe::class, 'safe_id');
    }

    public function serviceAccount()
    {
        return $this->belongsTo(ServiceAccount::class, 'service_account_id');
    }

    /** Formal stock document linked to this purchase revision (reference_type = purchase). */
    public function stockDocument()
    {
        return $this->hasOne(StockTransaction::class, 'reference_id')
            ->where('reference_type', 'purchase');
    }
}
