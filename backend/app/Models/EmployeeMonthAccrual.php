<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeMonthAccrual extends Model
{
    protected $fillable = [
        'employee_id',
        'month',
        'year',
        'amount',
        'daily_entry_id',
        'user_id',
    ];

    protected $casts = [
        'amount' => 'float',
        'month' => 'integer',
        'year' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function dailyEntry(): BelongsTo
    {
        return $this->belongsTo(DailyEntry::class);
    }
}
