<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorporateSalesLead extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'company_name',
        'country_name',
        'company_facebook',
        'company_instagram',
        'company_linkedin',
        'company_website',
        'corporate_sales_industry_id',
        'corporate_sales_lead_source_id',
        'corporate_sales_lead_tool_id',
        'user_id'
    ];

    public function contact()
    {
        return $this->hasMany(CorporateSalesContact::class);
    }

    public function tracking()
    {
        return $this->hasMany(CorporateSalesTracking::class);
    }

    public function progress()
    {
        return $this->hasMany(CorporateSalesProgress::class);
    }

    public function notes()
    {
        return $this->hasMany(CorporateSalesNotes::class);
    }

    public function industry()
    {
        return $this->belongsTo(CorporateSalesIndustry::class, 'corporate_sales_industry_id');
    }

    public function source()
    {
        return $this->belongsTo(CorporateSalesLeadSource::class, 'corporate_sales_lead_source_id');
    }

    public function tool()
    {
        return $this->belongsTo(CorporateSalesLeadTool::class, 'corporate_sales_lead_tool_id');
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }


}
