<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorporateSalesLeadRecommender extends Model
{
    use HasFactory;

    protected $fillable = [
        'corporate_sales_lead_id',
        'reminder_date',
        'notes',
        'is_done',
        'user_id',
    ];

    protected $casts = [
        'reminder_date' => 'date',
        'is_done' => 'boolean',
    ];

    public function lead()
    {
        return $this->belongsTo(CorporateSalesLead::class, 'corporate_sales_lead_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
