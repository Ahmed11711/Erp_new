<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    public static function boot()
    {
        parent::boot();

        static::created(function($expense) {
            $expense->expense_number .= 'EX' . $expense->id;
            $expense->save();
        });
    }

    protected $fillable = [
        'expense_type',
        'bank_id',
        'kind_id',
        'expens_statement',
        'amount',
        'note',
        'address',
        'expense_image',
        'user_id',
        'ref',
        'status',
        'created_at',
    ];

    public function bank(){
        return $this->belongsTo(Bank::class);
    }

    public function kind(){
        return $this->belongsTo(ExpenseKind::class);
    }

}
