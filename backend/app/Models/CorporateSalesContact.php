<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorporateSalesContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'contact_linkedin',
        'corporate_sales_lead_id',
        'user_id',
    ];

    public function emails()
    {
        return $this->hasMany(CorporateSalesContactEmail::class);
    }

    public function phones()
    {
        return $this->hasMany(CorporateSalesContactNumber::class);
    }

    public function lead(){
        return $this->belongsTo(CorporateSalesLead::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
