<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeMonthAccrual extends Model
{
    protected $fillable = [
        'employee_id',
        'employee_name',
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

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            if ($model->employee_id && blank($model->employee_name)) {
                $model->employee_name = optional(Employee::find($model->employee_id))->name;
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function dailyEntry(): BelongsTo
    {
        return $this->belongsTo(DailyEntry::class);
    }
}
