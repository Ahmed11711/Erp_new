<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'order',
        'color',
        'requires_follow_up',
    ];

    /**
     * Get the leads for this status.
     */
    public function leads()
    {
        return $this->hasMany(CorporateSalesLead::class);
    }

    /**
     * Scope to get statuses ordered by their order field.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc');
    }

    /**
     * Get the default status (New Lead).
     */
    public static function getDefault()
    {
        return static::where('name', 'New Lead')->first();
    }

    /**
     * Get the Follow-Up status.
     */
    public static function getFollowUpStatus()
    {
        return static::where('name', 'Follow-Up')->first();
    }

    /**
     * Check if this status requires follow-up action.
     */
    public function requiresFollowUp()
    {
        return $this->requires_follow_up;
    }

    /**
     * Get active statuses (excluding Archived).
     */
    public static function getActive()
    {
        return static::where('name', '!=', 'Archived')->ordered();
    }

    /**
     * Get closed/won statuses.
     */
    public static function getClosedWon()
    {
        return static::whereIn('name', ['Converted', 'Dealt / Closed Won'])->ordered();
    }

    /**
     * Get lost/closed statuses.
     */
    public static function getClosedLost()
    {
        return static::whereIn('name', ['Not Qualified', 'Wrong Data', 'Archived'])->ordered();
    }
}
