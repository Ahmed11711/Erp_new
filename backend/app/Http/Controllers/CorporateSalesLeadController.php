<?php

namespace App\Http\Controllers;

use App\Models\CorporateSalesContact;
use App\Models\CorporateSalesContactEmail;
use App\Models\CorporateSalesContactNumber;
use App\Models\CorporateSalesIndustry;
use App\Models\CorporateSalesLead;
use App\Models\CorporateSalesNotes;
use App\Models\CorporateSalesProgress;
use App\Models\CorporateSalesTracking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CorporateSalesLeadController extends Controller
{

    public function index()
    {
        $itemsPerPage = request('itemsPerPage') ?? 10;

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

        $data = $query->with([
            'contact.emails.user:id,name',
            'contact.phones.user:id,name',
            'contact.user:id,name',
            'tracking.user:id,name',
            'progress.user:id,name',
            'notes.user:id,name',
            'industry',
            'source',
            'tool',
            'user:id,name',
        ])->orderBy('id', 'desc')->paginate($itemsPerPage);

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
            'industry',
            'source',
            'tool',
            'user:id,name',
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
                'company_size' => $request->company_size,
                'annual_revenue' => $request->annual_revenue,
                'industry_sector' => $request->industry_sector,
                'geographic_region' => $request->geographic_region,
                'main_competitors' => $request->main_competitors,
                'lead_priority' => $request->lead_priority,
                'required_products' => $request->required_products,
                'expected_budget' => $request->expected_budget,
                'project_timeline' => $request->project_timeline,
                'decision_maker' => $request->decision_maker,
                'notes' => $request->notes,
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

}
