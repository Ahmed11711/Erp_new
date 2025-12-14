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

}
