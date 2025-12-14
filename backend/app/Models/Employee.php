<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable=[
        "name",
        "code",
        "level",
        "department",
        "fixed_salary",
        "salary_type",
        "working_hours",
        "acc_no",
    ];

    public function merits()
    {
        return $this->hasMany(EmployeeMerits::class);
    }

    public function subtraction()
    {
        return $this->hasMany(EmployeeSubtraction::class);
    }

    public function advance_payment()
    {
        return $this->hasMany(EmployeeAdvancePayment::class);
    }

    public function extraHours()
    {
        return $this->hasMany(EmployeeExtraHour::class);
    }

    public function fingerPrint()
    {
        return $this->hasMany(EmployeeFingerPrintSheet::class);
    }

    public function salaryPaid()
    {
        return $this->hasMany(EmployeeMonthPaid::class);
    }
}
