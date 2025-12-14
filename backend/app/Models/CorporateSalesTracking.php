<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorporateSalesTracking extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'details',
        'old_value',
        'new_value',
        'corporate_sales_lead_id',
        'user_id',
    ];

    public function lead(){
        return $this->belongsTo(CorporateSalesLead::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
