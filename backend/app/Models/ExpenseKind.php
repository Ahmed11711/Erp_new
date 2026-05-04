<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseKind extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_type',
        'expense_kind',
        'tree_account_id',
    ];

    public function treeAccount()
    {
        return $this->belongsTo(TreeAccount::class, 'tree_account_id');
    }
}
