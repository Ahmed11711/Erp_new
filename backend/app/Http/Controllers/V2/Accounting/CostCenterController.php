<?php

namespace App\Http\Controllers\V2\Accounting;

use App\Http\Controllers\Controller;
use App\Models\CostCenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CostCenterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = CostCenter::with(['parent', 'responsiblePerson']);

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('name_en', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 25);
        $costCenters = $query->orderBy('code')->paginate($perPage);

        return response()->json($costCenters, 200);
    }

    /**
     * Get cost center tree
     */
    public function tree()
    {
        $costCenters = CostCenter::with(['children', 'responsiblePerson'])
            ->whereNull('parent_id')
            ->orderBy('code')
            ->get();

        return response()->json($costCenters, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'name_en' => 'nullable|string',
            'type' => 'required|in:main,sub',
            'parent_id' => 'nullable|exists:cost_centers,id',
            'responsible_person_id' => 'nullable|exists:employees,id',
            'location' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'duration' => 'nullable|string',
            'value' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // Generate code
            if ($request->type === 'main') {
                $lastMain = CostCenter::where('type', 'main')
                    ->whereNull('parent_id')
                    ->orderByDesc('code')
                    ->first();
                $code = $lastMain ? $lastMain->code + 1 : 1;
            } else {
                $parent = CostCenter::find($request->parent_id);
                $lastSub = CostCenter::where('parent_id', $request->parent_id)
                    ->orderByDesc('code')
                    ->first();
                $code = $lastSub ? $lastSub->code + 1 : ($parent->code * 10 + 1);
            }

            $costCenter = CostCenter::create([
                'name' => $request->name,
                'name_en' => $request->name_en,
                'code' => $code,
                'type' => $request->type,
                'parent_id' => $request->parent_id,
                'responsible_person_id' => $request->responsible_person_id,
                'location' => $request->location,
                'phone' => $request->phone,
                'email' => $request->email,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'duration' => $request->duration,
                'value' => $request->value ?? 0,
            ]);

            DB::commit();
            return response()->json([
                'message' => 'تم إنشاء مركز التكلفة بنجاح',
                'data' => $costCenter->load(['parent', 'responsiblePerson'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $costCenter = CostCenter::with(['parent', 'children', 'responsiblePerson'])->find($id);
        
        if (!$costCenter) {
            return response()->json(['message' => 'مركز التكلفة غير موجود'], 404);
        }

        return response()->json($costCenter, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $costCenter = CostCenter::find($id);
        
        if (!$costCenter) {
            return response()->json(['message' => 'مركز التكلفة غير موجود'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string',
            'name_en' => 'nullable|string',
            'responsible_person_id' => 'nullable|exists:employees,id',
            'location' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'duration' => 'nullable|string',
            'value' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $costCenter->update($request->only([
            'name', 'name_en', 'responsible_person_id', 'location', 
            'phone', 'email', 'start_date', 'end_date', 'duration', 'value'
        ]));

        return response()->json([
            'message' => 'تم تحديث مركز التكلفة بنجاح',
            'data' => $costCenter->load(['parent', 'responsiblePerson'])
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $costCenter = CostCenter::find($id);
        
        if (!$costCenter) {
            return response()->json(['message' => 'مركز التكلفة غير موجود'], 404);
        }

        if ($costCenter->children()->count() > 0) {
            return response()->json(['message' => 'لا يمكن حذف مركز التكلفة لأنه يحتوي على مراكز فرعية'], 422);
        }

        $costCenter->delete();

        return response()->json(['message' => 'تم حذف مركز التكلفة بنجاح'], 200);
    }
}

