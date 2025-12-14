<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorporateSalesProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'progress',
        'file',
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
