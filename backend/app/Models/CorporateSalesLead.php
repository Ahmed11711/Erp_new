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
        'contact_title',
        'contact_department',
        'country_name',
        'company_facebook',
        'company_instagram',
        'company_linkedin',
        'company_website',
        'corporate_sales_industry_id',
        'corporate_sales_lead_source_id',
        'corporate_sales_lead_tool_id',
        'lead_status_id',
        'company_size',
        'annual_revenue',
        'industry_sector',
        'geographic_region',
        'main_competitors',
        'lead_priority',
        'required_products',
        'expected_budget',
        'project_timeline',
        'decision_maker',
        'notes',
        'next_action_type',
        'next_action_date',
        'next_action_notes',
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

    public function status()
    {
        return $this->belongsTo(LeadStatus::class, 'lead_status_id');
    }

    /**
     * Scope to get leads by status.
     */
    public function scopeByStatus($query, $statusName)
    {
        return $query->whereHas('status', function ($query) use ($statusName) {
            $query->where('name', $statusName);
        });
    }

    /**
     * Scope to get leads that need follow-up.
     */
    public function scopeNeedsFollowUp($query)
    {
        return $query->whereHas('status', function ($query) {
            $query->where('requires_follow_up', true);
        })->whereNotNull('next_action_date');
    }

    /**
     * Scope to get overdue follow-ups.
     */
    public function scopeOverdueFollowUp($query)
    {
        return $query->needsFollowUp()->where('next_action_date', '<', now());
    }

    /**
     * Scope to get today's follow-ups.
     */
    public function scopeTodayFollowUp($query)
    {
        return $query->needsFollowUp()->where('next_action_date', '=', now()->format('Y-m-d'));
    }

    /**
     * Scope to get upcoming follow-ups.
     */
    public function scopeUpcomingFollowUp($query)
    {
        return $query->needsFollowUp()->where('next_action_date', '>', now());
    }

    /**
     * Check if lead needs follow-up.
     */
    public function needsFollowUp()
    {
        return $this->status && $this->status->requires_follow_up && $this->next_action_date;
    }

    /**
     * Check if follow-up is overdue.
     */
    public function isFollowUpOverdue()
    {
        return $this->needsFollowUp() && $this->next_action_date < now()->format('Y-m-d');
    }

    /**
     * Get follow-up status text.
     */
    public function getFollowUpStatusText()
    {
        if (!$this->needsFollowUp()) {
            return null;
        }

        if ($this->isFollowUpOverdue()) {
            return 'Overdue';
        }

        if ($this->next_action_date == now()->format('Y-m-d')) {
            return 'Today';
        }

        return 'Upcoming';
    }


}
