<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_id',
        'expense_type',
        'kind_id',
        'amount',
        'sort_order',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function kind(): BelongsTo
    {
        return $this->belongsTo(ExpenseKind::class, 'kind_id');
    }
}
