<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreeAccountAudit extends Model
{
    protected $fillable = [
        'tree_account_id',
        'action',
        'performed_by',
        'account_code',
        'account_name',
        'parent_id',
        'changes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function treeAccount(): BelongsTo
    {
        return $this->belongsTo(TreeAccount::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
