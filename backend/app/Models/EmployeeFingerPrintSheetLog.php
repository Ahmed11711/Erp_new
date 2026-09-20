<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeFingerPrintSheetLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'finger_print_sheet_id',
        'user_id',
        'user_name',
        'action',
        'changes',
        'note',
        'created_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    public function fingerPrintSheet(): BelongsTo
    {
        return $this->belongsTo(EmployeeFingerPrintSheet::class, 'finger_print_sheet_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
