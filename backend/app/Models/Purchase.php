<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;
/**
 * Enables us to hook into model event's
 *
 * @return void
 */
// public static function boot()
// {
//     parent::boot();

//     static::created(function($invoice) {
//         $invoice->invoice_number .= 'PO' . $invoice->id;
//         $invoice->save();
//     });
// }
    public static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            $lastInvoice = self::latest()->first();
            if ($lastInvoice && preg_match('/PO(\d+)/', $lastInvoice->invoice_number, $matches)) {
                $number = intval($matches[1]) + 1;
            } else {
                $number = 1;
            }
            $invoice->invoice_number = 'PO' . $number;
        });
    }

    protected $fillable = [
        'invoice_number',
        'supplier_id',
        'supplierpay_id',
        'invoice_type',
        'receipt_date',
        'total_price',
        'paid_amount',
        'due_amount',
        'transport_cost',
        'invoice_image',
        'price_edited',
        'bank_id',
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
}
