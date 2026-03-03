<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cimmitment extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'date',
        'deserved_amount',
        'payee_type',
        'supplier_id',
        'payee_name',
        'expense_account_id',
        'liability_account_id',
        'status',
        'paid_amount',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'deserved_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PAID = 'paid';

    public const PAYEE_SUPPLIER = 'supplier';
    public const PAYEE_OTHER = 'other';

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function expenseAccount()
    {
        return $this->belongsTo(TreeAccount::class, 'expense_account_id');
    }

    public function liabilityAccount()
    {
        return $this->belongsTo(TreeAccount::class, 'liability_account_id');
    }

    public function accountEntries()
    {
        return $this->hasMany(AccountEntry::class, 'cimmitment_id');
    }

    /**
     * الحصول على اسم الجهة المسددة
     */
    public function getPayeeDisplayNameAttribute(): string
    {
        if ($this->payee_type === self::PAYEE_SUPPLIER && $this->supplier) {
            return $this->supplier->supplier_name ?? 'مورد #' . $this->supplier_id;
        }
        return $this->payee_name ?? 'جهة أخرى';
    }

    /**
     * المبلغ المتبقي المستحق
     */
    public function getRemainingAmountAttribute(): float
    {
        return (float) $this->deserved_amount - (float) $this->paid_amount;
    }
}
