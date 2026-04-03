<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TreeAccount extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'balance' => 'decimal:2',
        'debit_balance' => 'decimal:2',
        'credit_balance' => 'decimal:2',
        'is_trading_account' => 'boolean',
        'detail_type' => 'string',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function mainAccount()
    {
        return $this->belongsTo(self::class, 'main_account_id');
    }

    public function accountEntries()
    {
        return $this->hasMany(AccountEntry::class, 'tree_account_id');
    }

    /**
     * Total balance = debit - credit (مطابق لأفضل أنظمة المحاسبة)
     */
    public function getTotalBalanceAttribute()
    {
        return (float) ($this->debit_balance ?? 0) - (float) ($this->credit_balance ?? 0);
    }

    /**
     * Prefer a leaf revenue account so postings appear in reports that aggregate leaf accounts only.
     */
    public static function resolveSalesRevenueAccount(): ?self
    {
        $acc = static::where('detail_type', 'sales')->whereDoesntHave('children')->first();
        if ($acc) {
            return $acc;
        }

        return static::where('type', 'revenue')
            ->where(function ($q) {
                $q->where('name', 'like', '%مبيعات%')
                    ->orWhere('name_en', 'like', '%sales%');
            })
            ->whereDoesntHave('children')
            ->orderByRaw('LENGTH(code) DESC')
            ->orderBy('code')
            ->first();
    }

    /**
     * Prefer a leaf COGS / cost-of-sales expense account.
     */
    public static function resolveCogsAccount(): ?self
    {
        $acc = static::where('detail_type', 'cogs')->whereDoesntHave('children')->first();
        if ($acc) {
            return $acc;
        }

        $acc = static::where('type', 'expense')
            ->where(function ($q) {
                $q->where('name', 'like', '%تكلفة%')
                    ->orWhere('name', 'like', '%تكاليف%')
                    ->orWhere('name', 'like', '%تكلفة المبيعات%')
                    ->orWhere('name', 'like', '%تكلفة البضاعة%')
                    ->orWhere('name_en', 'like', '%cost%')
                    ->orWhere('name_en', 'like', '%cogs%');
            })
            ->whereDoesntHave('children')
            ->orderByRaw('LENGTH(code) DESC')
            ->orderBy('code')
            ->first();

        return $acc;
    }

    /**
     * Prefer a leaf asset account for inventory (stock) used in COGS journal pairs.
     */
    public static function resolveInventoryAccount(): ?self
    {
        $acc = static::where('detail_type', 'inventory')->whereDoesntHave('children')->first();
        if ($acc) {
            return $acc;
        }

        return static::where('type', 'asset')
            ->where(function ($q) {
                $q->where('name', 'like', '%مخزون%')
                    ->orWhere('name_en', 'like', '%inventory%')
                    ->orWhere('name_en', 'like', '%stock%');
            })
            ->whereDoesntHave('children')
            ->orderByRaw('LENGTH(code) DESC')
            ->orderBy('code')
            ->first();
    }

    /**
     * حساب موازنة لرصيد مخزون افتتاحي (دائن) — حقوق ملكية أو جاري.
     */
    public static function resolveOpeningInventoryOffsetAccount(): ?self
    {
        $acc = static::where('detail_type', 'opening_inventory_offset')->whereDoesntHave('children')->first();
        if ($acc) {
            return $acc;
        }

        return static::where('type', 'equity')
            ->where(function ($q) {
                $q->where('name', 'like', '%رأس المال%')
                    ->orWhere('name', 'like', '%جاري%')
                    ->orWhere('name_en', 'like', '%equity%');
            })
            ->whereDoesntHave('children')
            ->orderBy('id')
            ->first()
            ?? static::where('type', 'equity')->whereDoesntHave('children')->orderBy('id')->first();
    }
}