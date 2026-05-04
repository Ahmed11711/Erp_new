<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeMonthPaid extends Model
{
    use HasFactory;

    protected $fillable=[
        'amount',
        'bank_id',
        'safe_id',
        'service_account_id',
        'payment_source',
        'details',
        'month',
        'year',
        'employee_id',
        'user_id',
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
