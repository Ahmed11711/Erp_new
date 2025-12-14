<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeSubtraction extends Model
{
    use HasFactory;

    protected $fillable=[
        "type",
        "amount",
        "reason",
        'month',
        'year',
        'employee_id',
        'user_id',
        'reviewed',
        'absence_status',
        'absence_count',
        'reviewed',

    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
