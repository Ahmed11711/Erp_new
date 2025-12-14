<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeExtraHour extends Model
{
    use HasFactory;

    protected $fillable=[
        "hours",
        'month',
        'year',
        'employee_id',
        'user_id',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

}
