<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeFingerPrintSheet extends Model
{
    use HasFactory;

    protected $fillable=[
        "acc_no",
        "employee_id",
        "date",
        "check_in",
        "check_out",
        "hours",
        "iso_date",
        "time_in",
        "time_out",
        "hours_permission",
        "vacation",
        "vacation_reason",
        "reviewed",
        "is_overTime_removed",
        "absence_deduction",
        "times",
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * MySQL ENUM treats a numeric 2 as the 2nd option ('1.5'), not the value '2'.
     * Always persist the label as a string.
     */
    public function setAbsenceDeductionAttribute(mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['absence_deduction'] = null;
            return;
        }

        $this->attributes['absence_deduction'] = (string) $value;
    }

    public function logs()
    {
        return $this->hasMany(EmployeeFingerPrintSheetLog::class, 'finger_print_sheet_id')->orderByDesc('created_at');
    }

}
