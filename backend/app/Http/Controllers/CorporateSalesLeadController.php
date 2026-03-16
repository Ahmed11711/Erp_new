<?php

namespace App\Http\Controllers;

use App\Models\CorporateSalesContact;
use App\Models\CorporateSalesContactEmail;
use App\Models\CorporateSalesContactNumber;
use App\Models\CorporateSalesIndustry;
use App\Models\CorporateSalesLead;
use App\Models\CorporateSalesLeadRecommender;
use App\Models\CorporateSalesNotes;
use App\Models\CorporateSalesProgress;
use App\Models\CorporateSalesTracking;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CorporateSalesLeadController extends Controller
{

    public function index()
    {
        $itemsPerPage = request('itemsPerPage') ?? 10;
        $sortBy = request('sortBy') ?? 'id';
        $sortOrder = request('sortOrder') ?? 'desc';

        $query = CorporateSalesLead::query();

        if ($industryId = request('leadIndustry')) {
            $query->where('corporate_sales_industry_id', $industryId);
        }

        if ($sourceId = request('leadSource')) {
            $query->where('corporate_sales_lead_source_id', $sourceId);
        }

        if ($toolId = request('leadTool')) {
            $query->where('corporate_sales_lead_tool_id', $toolId);
        }

        if ($country = request('country')) {
            $query->where('country_name', $country);
        }

        if ($statusId = request('statusId')) {
            $query->where('lead_status_id', $statusId);
        }

        // Filter by user (lead owner or user who did activity)
        if ($userId = request('userId')) {
            $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhereHas('tracking', function ($tq) use ($userId) {
                        $tq->where('user_id', $userId);
                    });
            });
        }

        // Filter by activity date (leads with activity in date range)
        $activityDateFrom = request('activityDateFrom');
        $activityDateTo = request('activityDateTo');
        if ($activityDateFrom || $activityDateTo) {
            $query->where(function ($q) use ($activityDateFrom, $activityDateTo) {
                // Lead created in range
                $q->where(function ($sub) use ($activityDateFrom, $activityDateTo) {
                    if ($activityDateFrom) {
                        $sub->whereDate('corporate_sales_leads.created_at', '>=', $activityDateFrom);
                    }
                    if ($activityDateTo) {
                        $sub->whereDate('corporate_sales_leads.created_at', '<=', $activityDateTo);
                    }
                });
                // OR has tracking in range
                $q->orWhereHas('tracking', function ($tq) use ($activityDateFrom, $activityDateTo) {
                    if ($activityDateFrom) {
                        $tq->whereDate('created_at', '>=', $activityDateFrom);
                    }
                    if ($activityDateTo) {
                        $tq->whereDate('created_at', '<=', $activityDateTo);
                    }
                });
            });
        }

        // Sort by last activity, date, or id
        if ($sortBy === 'last_activity') {
            $query->selectRaw('corporate_sales_leads.*, COALESCE(
                (SELECT MAX(created_at) FROM corporate_sales_trackings WHERE corporate_sales_lead_id = corporate_sales_leads.id),
                corporate_sales_leads.created_at
            ) as last_activity_at')
                ->orderBy('last_activity_at', $sortOrder);
        } elseif ($sortBy === 'date') {
            $query->orderBy('date', $sortOrder);
        } else {
            $query->orderBy('id', $sortOrder);
        }

        $data = $query->with([
            'contact.emails.user:id,name',
            'contact.phones.user:id,name',
            'contact.user:id,name',
            'tracking.user:id,name',
            'progress.user:id,name',
            'notes.user:id,name',
            'recommenders.user:id,name',
            'industry',
            'source',
            'tool',
            'user:id,name',
            'status',
        ])->paginate($itemsPerPage);

        return response()->json($data);
    }


    public function show($id)
    {
        $data = CorporateSalesLead::where('id',$id)->with([
            'contact.emails.user:id,name',
            'contact.phones.user:id,name',
            'contact.user:id,name',
            'tracking.user:id,name',
            'progress.user:id,name',
            'notes.user:id,name',
            'recommenders.user:id,name',
            'industry',
            'source',
            'tool',
            'user:id,name',
            'status',
        ])->first();

        return response()->json($data, 200);
    }

    public function store(Request $request){

        DB::beginTransaction();
        try{

            $corporate_sales_industry_id = $request->industry;
            if (gettype($request->industry) === "string") {
                $industry = CorporateSalesIndustry::create([
                    'name' => $request->industry,
                    'user_id' => auth()->user()->id
                ]);
                $corporate_sales_industry_id = $industry->id;
            }

            $lead = CorporateSalesLead::create([
                'company_facebook' => $request->company_facebook,
                'company_instagram' => $request->company_instagram,
                'company_linkedin' => $request->company_linkedin,
                'company_name' => $request->company_name,
                'company_website' => $request->company_website,
                'country_name' => $request->country,
                'date' => $request->date,
                'contact_title' => $request->contact_title,
                'contact_department' => $request->contact_department,
                'corporate_sales_industry_id' => $corporate_sales_industry_id,
                'corporate_sales_lead_source_id' => $request->lead_source,
                'corporate_sales_lead_tool_id' => $request->lead_tool,
                'lead_status_id' => $request->lead_status_id,
                'next_action_type' => $request->next_action_type,
                'next_action_date' => $request->next_action_date,
                'next_action_notes' => $request->next_action_notes,
                'user_id' => auth()->user()->id
            ]);

            CorporateSalesTracking::create([
                'type' => 'add',
                'old_value' => null,
                'new_value' => $lead->company_name,
                'details' => 'add New Lead',
                'corporate_sales_lead_id' => $lead->id,
                'user_id' => auth()->user()->id,
            ]);

            $note = CorporateSalesNotes::create([
                'note' => $request->notes,
                'corporate_sales_lead_id' => $lead->id,
                'user_id' => auth()->user()->id
            ]);

            CorporateSalesTracking::create([
                'type' => 'add',
                'old_value' => null,
                'new_value' => $note->note,
                'details' => 'add Note',
                'corporate_sales_lead_id' => $lead->id,
                'user_id' => auth()->user()->id,
            ]);

            $contacts = $request->input('contacts');

            foreach ($contacts as $elm) {
                $contact = CorporateSalesContact::create([
                    'name' => $elm['contact_name'],
                    'contact_linkedin' => $elm['contact_linkedin'],
                    'corporate_sales_lead_id' => $lead->id,
                    'user_id' => auth()->user()->id
                ]);

                CorporateSalesTracking::create([
                    'type' => 'add',
                    'old_value' => null,
                    'new_value' => $contact->name,
                    'details' => 'add Contact',
                    'corporate_sales_lead_id' => $lead->id,
                    'user_id' => auth()->user()->id,
                ]);

                $phones =  $elm['phones'];
                $emails =  $elm['emails'];

                foreach ($phones as $phone) {
                    $phone = CorporateSalesContactNumber::create([
                        'dial_code' => $phone['dial_code'],
                        'contact_number' => $phone['contact_number'],
                        'corporate_sales_contact_id' => $contact['id'],
                        'user_id' => auth()->user()->id
                    ]);

                    CorporateSalesTracking::create([
                        'type' => 'add',
                        'old_value' => null,
                        'new_value' => $phone->dial_code.' '.$phone->contact_number,
                        'details' => 'add Phone',
                        'corporate_sales_lead_id' => $lead->id,
                        'user_id' => auth()->user()->id,
                    ]);
                }

                foreach ($emails as $email) {
                    $email = CorporateSalesContactEmail::create([
                        'email' => $email,
                        'corporate_sales_contact_id' => $contact['id'],
                        'user_id' => auth()-> user()-> id
                    ]);

                    CorporateSalesTracking::create([
                        'type' => 'add',
                        'old_value' => null,
                        'new_value' => $email->email,
                        'details' => 'add Email',
                        'corporate_sales_lead_id' => $lead->id,
                        'user_id' => auth()->user()->id,
                    ]);
                }

            }

            DB::commit();
            return response()->json(['message'=>'success'], 201);

        }catch(\Illuminate\Validation\ValidationException $e){
             DB::rollback();
             return response()->json(['message' => $e->validator->errors()->first()], 422);
        }catch(\Exception $e){
            DB::rollback();
            Log::error('Error in CorporateSalesLeadController@store: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['message'=>$e->getMessage()], 500);
        }
    }


    public function edit(Request $request){

        DB::beginTransaction();
        try{

            $type = $request->type;
            $value = $request->value;

            if ($type === 'progress') {
                $img_name ='';
                if($request->hasFile('file')){
                    $img = $request->file('file');
                    $img_name = time() . '.' . $img->extension();
                    $img->move(public_path('images'), $img_name);
                    $value = $img_name;
                }

                CorporateSalesProgress::create([
                    'progress' => $request->value,
                    'corporate_sales_lead_id' =>$request->lead_id,
                    'file' => $img_name,
                    'user_id' => auth()->user()->id
                ]);

                CorporateSalesTracking::create([
                    'type' => 'add',
                    'old_value' => null,
                    'new_value' => $value,
                    'details' => 'add Progress',
                    'corporate_sales_lead_id' => $request->lead_id,
                    'user_id' => auth()->user()->id,
                ]);
            }

            if ($type === 'note') {
                CorporateSalesNotes::create([
                    'note' => $value,
                    'corporate_sales_lead_id' =>$request->lead_id,
                    'user_id' => auth()->user()->id
                ]);

                CorporateSalesTracking::create([
                    'type' => 'add',
                    'old_value' => null,
                    'new_value' => $value,
                    'details' => 'add Note',
                    'corporate_sales_lead_id' => $request->lead_id,
                    'user_id' => auth()->user()->id,
                ]);
            }

            if ($type === 'email') {
                CorporateSalesContactEmail::create([
                    'email' => $value,
                    'corporate_sales_contact_id' => $request->contactId,
                    'user_id' => auth()-> user()-> id
                ]);

                CorporateSalesTracking::create([
                    'type' => 'add',
                    'old_value' => null,
                    'new_value' => $value,
                    'details' => 'add Email',
                    'corporate_sales_lead_id' => $request->lead_id,
                    'user_id' => auth()->user()->id,
                ]);
            }

            if ($type === 'phone') {

                $phone = CorporateSalesContactNumber::create([
                    'dial_code' => $request->dialCode,
                    'contact_number' => $request->phone,
                    'corporate_sales_contact_id' => $request->contactId,
                    'user_id' => auth()->user()->id
                ]);

                CorporateSalesTracking::create([
                    'type' => 'add',
                    'old_value' => null,
                    'new_value' => $request->dialCode.' '.$request->phone,
                    'details' => 'add Phone',
                    'corporate_sales_lead_id' => $request->lead_id,
                    'user_id' => auth()->user()->id,
                ]);
            }

            if ($type === 'contact') {
                $contacts = $request->data['contacts'];
                foreach ($contacts as $elm) {
                    $contact = CorporateSalesContact::create([
                        'name' => $elm['contact_name'],
                        'contact_linkedin' => $elm['contact_linkedin'],
                        'corporate_sales_lead_id' => $request->lead_id,
                        'user_id' => auth()->user()->id
                    ]);

                    CorporateSalesTracking::create([
                        'type' => 'add',
                        'old_value' => null,
                        'new_value' => $contact->name,
                        'details' => 'add Contact',
                        'corporate_sales_lead_id' => $request->lead_id,
                        'user_id' => auth()->user()->id,
                    ]);

                    $phones =  $elm['phones'];
                    $emails =  $elm['emails'];

                    foreach ($phones as $phone) {
                        $phone = CorporateSalesContactNumber::create([
                            'dial_code' => $phone['dial_code'],
                            'contact_number' => $phone['contact_number'],
                            'corporate_sales_contact_id' => $contact['id'],
                            'user_id' => auth()->user()->id
                        ]);

                        CorporateSalesTracking::create([
                            'type' => 'add',
                            'old_value' => null,
                            'new_value' => $phone->dial_code.' '.$phone->contact_number,
                            'details' => 'add Phone',
                            'corporate_sales_lead_id' => $request->lead_id,
                            'user_id' => auth()->user()->id,
                        ]);
                    }

                    foreach ($emails as $email) {
                        $email = CorporateSalesContactEmail::create([
                            'email' => $email,
                            'corporate_sales_contact_id' => $contact['id'],
                            'user_id' => auth()-> user()-> id
                        ]);

                        CorporateSalesTracking::create([
                            'type' => 'add',
                            'old_value' => null,
                            'new_value' => $email->email,
                            'details' => 'add Email',
                            'corporate_sales_lead_id' => $request->lead_id,
                            'user_id' => auth()->user()->id,
                        ]);
                    }

                }

            }

            if ($type === 'edit Email') {
                $emailId = $request->emailId;

                $email = CorporateSalesContactEmail::find($emailId);

                $oldEmail = $email->email;

                $email->email = $value;
                $email->user_id = auth()->user()->id;

                CorporateSalesTracking::create([
                    'type' => 'edit',
                    'old_value' => $oldEmail,
                    'new_value' => $value,
                    'details' => 'edit Email',
                    'corporate_sales_lead_id' => $request->lead_id,
                    'user_id' => auth()->user()->id,
                ]);

                $email->save();
            }

            if ($type === 'edit Contact LinkedIn') {
                $id = $request->contactId;

                $find = CorporateSalesContact::find($id);

                $oldValue = $find->contact_linkedin;

                $find->contact_linkedin = $value;
                $find->user_id = auth()->user()->id;

                CorporateSalesTracking::create([
                    'type' => 'edit',
                    'old_value' => $oldValue,
                    'new_value' => $value,
                    'details' => 'edit Contact LinkedIn',
                    'corporate_sales_lead_id' => $request->lead_id,
                    'user_id' => auth()->user()->id,
                ]);

                $find->save();
            }

            if ($type === 'edit Contact Name') {
                $id = $request->contactId;

                $find = CorporateSalesContact::find($id);

                $oldValue = $find->name;

                $find->name = $value;
                $find->user_id = auth()->user()->id;

                CorporateSalesTracking::create([
                    'type' => 'edit',
                    'old_value' => $oldValue,
                    'new_value' => $value,
                    'details' => 'edit Contact Name',
                    'corporate_sales_lead_id' => $request->lead_id,
                    'user_id' => auth()->user()->id,
                ]);

                $find->save();
            }

            if ($type === 'edit Company Name') {
                $id = $request->lead_id;

                $find = CorporateSalesLead::find($id);

                $oldValue = $find->company_name;

                $find->company_name = $value;
                $find->user_id = auth()->user()->id;

                CorporateSalesTracking::create([
                    'type' => 'edit',
                    'old_value' => $oldValue,
                    'new_value' => $value,
                    'details' => $type,
                    'corporate_sales_lead_id' => $request->lead_id,
                    'user_id' => auth()->user()->id,
                ]);

                $find->save();
            }

            if ($type === 'edit Company LinkedIn') {
                $id = $request->lead_id;

                $find = CorporateSalesLead::find($id);

                $oldValue = $find->company_linkedin;

                $find->company_linkedin = $value;
                $find->user_id = auth()->user()->id;

                CorporateSalesTracking::create([
                    'type' => 'edit',
                    'old_value' => $oldValue,
                    'new_value' => $value,
                    'details' => $type,
                    'corporate_sales_lead_id' => $request->lead_id,
                    'user_id' => auth()->user()->id,
                ]);

                $find->save();
            }

            if ($type === 'edit Company Website') {
                $id = $request->lead_id;

                $find = CorporateSalesLead::find($id);

                $oldValue = $find->company_website;

                $find->company_website = $value;
                $find->user_id = auth()->user()->id;

                CorporateSalesTracking::create([
                    'type' => 'edit',
                    'old_value' => $oldValue,
                    'new_value' => $value,
                    'details' => $type,
                    'corporate_sales_lead_id' => $request->lead_id,
                    'user_id' => auth()->user()->id,
                ]);

                $find->save();
            }

            if ($type === 'edit Company Facebook') {
                $id = $request->lead_id;

                $find = CorporateSalesLead::find($id);

                $oldValue = $find->company_facebook;

                $find->company_facebook = $value;
                $find->user_id = auth()->user()->id;

                CorporateSalesTracking::create([
                    'type' => 'edit',
                    'old_value' => $oldValue,
                    'new_value' => $value,
                    'details' => $type,
                    'corporate_sales_lead_id' => $request->lead_id,
                    'user_id' => auth()->user()->id,
                ]);

                $find->save();
            }

            if ($type === 'edit Company Instagram') {
                $id = $request->lead_id;

                $find = CorporateSalesLead::find($id);

                $oldValue = $find->company_instagram;

                $find->company_instagram = $value;
                $find->user_id = auth()->user()->id;

                CorporateSalesTracking::create([
                    'type' => 'edit',
                    'old_value' => $oldValue,
                    'new_value' => $value,
                    'details' => $type,
                    'corporate_sales_lead_id' => $request->lead_id,
                    'user_id' => auth()->user()->id,
                ]);

                $find->save();
            }

            if ($type === 'edit Contact Title') {
                $id = $request->lead_id;

                $find = CorporateSalesLead::find($id);

                $oldValue = $find->contact_title;

                $find->contact_title = $value;
                $find->user_id = auth()->user()->id;

                CorporateSalesTracking::create([
                    'type' => 'edit',
                    'old_value' => $oldValue,
                    'new_value' => $value,
                    'details' => $type,
                    'corporate_sales_lead_id' => $request->lead_id,
                    'user_id' => auth()->user()->id,
                ]);

                $find->save();
            }

            if ($type === 'edit Contact Department') {
                $id = $request->lead_id;

                $find = CorporateSalesLead::find($id);

                $oldValue = $find->contact_department;

                $find->contact_department = $value;
                $find->user_id = auth()->user()->id;

                CorporateSalesTracking::create([
                    'type' => 'edit',
                    'old_value' => $oldValue,
                    'new_value' => $value,
                    'details' => $type,
                    'corporate_sales_lead_id' => $request->lead_id,
                    'user_id' => auth()->user()->id,
                ]);

                $find->save();
            }

            if ($type === 'recommender') {
                CorporateSalesLeadRecommender::create([
                    'corporate_sales_lead_id' => $request->lead_id,
                    'reminder_date' => $request->reminder_date,
                    'notes' => $request->notes ?? null,
                    'user_id' => auth()->user()->id,
                ]);

                CorporateSalesTracking::create([
                    'type' => 'add',
                    'old_value' => null,
                    'new_value' => 'تذكرة متابعة: ' . $request->reminder_date,
                    'details' => 'add Recommender',
                    'corporate_sales_lead_id' => $request->lead_id,
                    'user_id' => auth()->user()->id,
                ]);
            }

            if ($type === 'edit Phone') {
                $phoneId = $request->phoneId;

                $phone = CorporateSalesContactNumber::find($phoneId);

                $oldDialCode = $phone->dial_code;
                $oldContactNumber = $phone->contact_number;

                $phone->dial_code = $request->dialCode;
                $phone->contact_number = $request->phone;
                $phone->user_id = auth()->user()->id;

                CorporateSalesTracking::create([
                    'type' => 'edit',
                    'old_value' => $oldDialCode . ' ' . $oldContactNumber,
                    'new_value' => $request->dialCode . ' ' . $request->phone,
                    'details' => 'edit Phone',
                    'corporate_sales_lead_id' => $request->lead_id,
                    'user_id' => auth()->user()->id,
                ]);

                $phone->save();
            }

            DB::commit();
            return response()->json(['message'=>'success'], 200);

        }catch(\Exception $e){
            DB::rollback();
            Log::error('Error in CorporateSalesLeadController@edit: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['message'=>$e->getMessage()], 500);
        }
    }

    /**
     * Get users for filter dropdown: users with lead activity + department users.
     * Ensures the dropdown is never empty for Corparates/Shipping Management.
     */
    public function getLeadTeamUsers()
    {
        $userIds = CorporateSalesLead::pluck('user_id')
            ->merge(CorporateSalesTracking::pluck('user_id'))
            ->unique()
            ->filter();

        $users = User::where(function ($q) use ($userIds) {
            if ($userIds->isNotEmpty()) {
                $q->whereIn('id', $userIds);
            }
            $q->orWhereIn('department', ['Corparates', 'Shipping Management', 'Admin', 'Corporate', 'Corporate Sales']);
        })
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->unique('id')
            ->values();

        if ($users->isEmpty()) {
            $users = User::select('id', 'name')->orderBy('name')->get();
        }

        return response()->json($users);
    }

    /**
     * Get activity stats per user (who's working, activity count).
     */
    public function getLeadActivityStats(Request $request)
    {
        $activityDateFrom = $request->activityDateFrom;
        $activityDateTo = $request->activityDateTo;
        $userId = $request->userId;

        $trackingQuery = CorporateSalesTracking::query();
        if ($activityDateFrom) {
            $trackingQuery->whereDate('created_at', '>=', $activityDateFrom);
        }
        if ($activityDateTo) {
            $trackingQuery->whereDate('created_at', '<=', $activityDateTo);
        }
        if ($userId) {
            $trackingQuery->where('user_id', $userId);
        }

        $leadQuery = CorporateSalesLead::query();
        if ($activityDateFrom) {
            $leadQuery->whereDate('created_at', '>=', $activityDateFrom);
        }
        if ($activityDateTo) {
            $leadQuery->whereDate('created_at', '<=', $activityDateTo);
        }
        if ($userId) {
            $leadQuery->where('user_id', $userId);
        }

        $trackingCounts = (clone $trackingQuery)
            ->select('user_id', DB::raw('COUNT(*) as activity_count'), DB::raw('MAX(created_at) as last_activity_at'))
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $leadCounts = (clone $leadQuery)
            ->select('user_id', DB::raw('COUNT(*) as lead_count'))
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $userIds = $trackingCounts->keys()->merge($leadCounts->keys())->unique()->filter();
        $users = User::whereIn('id', $userIds)->select('id', 'name')->get()->keyBy('id');

        $stats = [];
        foreach ($userIds as $userId) {
            $tracking = $trackingCounts->get($userId);
            $lead = $leadCounts->get($userId);
            $activityCount = ($tracking->activity_count ?? 0) + ($lead->lead_count ?? 0);

            $lastActivity = $tracking->last_activity_at ?? null;
            if (!$lastActivity && $lead && ($lead->lead_count ?? 0) > 0) {
                $lastActivity = CorporateSalesLead::where('user_id', $userId)
                    ->when($activityDateFrom, fn($q) => $q->whereDate('created_at', '>=', $activityDateFrom))
                    ->when($activityDateTo, fn($q) => $q->whereDate('created_at', '<=', $activityDateTo))
                    ->max('created_at');
            }

            $stats[] = [
                'user_id' => $userId,
                'user_name' => $users->get($userId)?->name ?? 'Unknown',
                'activity_count' => (int) $activityCount,
                'last_activity_at' => $lastActivity,
            ];
        }

        usort($stats, fn($a, $b) => $b['activity_count'] <=> $a['activity_count']);

        return response()->json(['stats' => $stats]);
    }

    /**
     * Get pending recommenders (reminders) for the current user.
     * Returns leads with recommenders where reminder_date >= today.
     */
    public function getPendingRecommenders(Request $request)
    {
        $userId = $request->userId ?? auth()->id();
        $fromDate = $request->from_date ?? now()->format('Y-m-d');

        $recommenders = CorporateSalesLeadRecommender::where('user_id', $userId)
            ->where('is_done', false)
            ->whereDate('reminder_date', '>=', $fromDate)
            ->with(['lead:id,company_name', 'user:id,name'])
            ->orderBy('reminder_date')
            ->get();

        $count = $recommenders->count();

        return response()->json([
            'count' => $count,
            'recommenders' => $recommenders,
        ]);
    }

    /**
     * Delete a recommender.
     */
    public function deleteRecommender($id)
    {
        $recommender = CorporateSalesLeadRecommender::findOrFail($id);
        $recommender->delete();
        return response()->json(['message' => 'success'], 200);
    }

    /**
     * Toggle recommender done status.
     */
    public function toggleRecommenderDone($id)
    {
        $recommender = CorporateSalesLeadRecommender::findOrFail($id);
        $recommender->is_done = !$recommender->is_done;
        $recommender->save();
        return response()->json(['message' => 'success', 'is_done' => $recommender->is_done], 200);
    }

}
