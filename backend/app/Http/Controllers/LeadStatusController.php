<?php

namespace App\Http\Controllers;

use App\Models\LeadStatus;
use App\Models\CorporateSalesLead;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LeadStatusController extends Controller
{
    /**
     * Display a listing of lead statuses.
     */
    public function index(): JsonResponse
    {
        $statuses = LeadStatus::ordered()->get();
        return response()->json($statuses);
    }

    /**
     * Store a newly created lead status.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'order' => 'required|integer|unique:lead_statuses,order',
            'color' => 'nullable|string|max:7',
            'requires_follow_up' => 'boolean',
        ]);

        $status = LeadStatus::create($request->all());
        return response()->json($status, 201);
    }

    /**
     * Display the specified lead status.
     */
    public function show(LeadStatus $leadStatus): JsonResponse
    {
        return response()->json($leadStatus);
    }

    /**
     * Update the specified lead status.
     */
    public function update(Request $request, LeadStatus $leadStatus): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'order' => 'required|integer|unique:lead_statuses,order,' . $leadStatus->id,
            'color' => 'nullable|string|max:7',
            'requires_follow_up' => 'boolean',
        ]);

        $leadStatus->update($request->all());
        return response()->json($leadStatus);
    }

    /**
     * Remove the specified lead status.
     */
    public function destroy(LeadStatus $leadStatus): JsonResponse
    {
        // Check if status is being used by any leads
        if ($leadStatus->leads()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete status that is being used by leads'
            ], 422);
        }

        $leadStatus->delete();
        return response()->json(null, 204);
    }

    /**
     * Get active statuses (excluding Archived).
     */
    public function getActive(): JsonResponse
    {
        $statuses = LeadStatus::getActive()->get();
        return response()->json($statuses);
    }

    /**
     * Get closed/won statuses.
     */
    public function getClosedWon(): JsonResponse
    {
        $statuses = LeadStatus::getClosedWon()->get();
        return response()->json($statuses);
    }

    /**
     * Get lost/closed statuses.
     */
    public function getClosedLost(): JsonResponse
    {
        $statuses = LeadStatus::getClosedLost()->get();
        return response()->json($statuses);
    }

    /**
     * Update lead status with next action details.
     */
    public function updateLeadStatus(Request $request, CorporateSalesLead $lead): JsonResponse
    {
        $request->validate([
            'lead_status_id' => 'required|exists:lead_statuses,id',
            'next_action_type' => 'nullable|string|max:255',
            'next_action_date' => 'nullable|date',
            'next_action_notes' => 'nullable|string',
        ]);

        $oldStatus = $lead->lead_status_id;
        $newStatus = $request->lead_status_id;

        // Update lead status and next action
        $lead->update([
            'lead_status_id' => $newStatus,
            'next_action_type' => $request->next_action_type,
            'next_action_date' => $request->next_action_date,
            'next_action_notes' => $request->next_action_notes,
        ]);

        // Create tracking record
        $status = LeadStatus::find($newStatus);
        \App\Models\CorporateSalesTracking::create([
            'type' => 'status_change',
            'details' => "Status changed from {$lead->status->name} to {$status->name}",
            'old_value' => $oldStatus,
            'new_value' => $newStatus,
            'corporate_sales_lead_id' => $lead->id,
            'user_id' => auth()->id(),
        ]);

        return response()->json($lead->load('status'));
    }

    /**
     * Get leads that need follow-up.
     */
    public function getFollowUpLeads(): JsonResponse
    {
        $leads = CorporateSalesLead::whereHas('status', function ($query) {
            $query->where('requires_follow_up', true);
        })
        ->with(['status', 'user'])
        ->orderBy('next_action_date', 'asc')
        ->get();

        return response()->json($leads);
    }
}
