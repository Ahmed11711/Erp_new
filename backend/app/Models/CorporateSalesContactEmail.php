<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorporateSalesContactEmail extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'corporate_sales_contact_id',
        'user_id',
    ];

    public function contact(){
        return $this->belongsTo(CorporateSalesContact::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
