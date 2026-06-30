<?php

namespace App\Http\Controllers;

use App\Models\EmployeeSubtraction;
use App\Services\Hr\EmployeeAbsencePermissionService;
use Illuminate\Http\Request;
use App\Models\EmployeeMonthPaid;
use Illuminate\Support\Carbon;


class EmployeeSubtractionController extends Controller
{

    public function absenceStatus(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|integer|exists:employee_subtractions,id',
            'absence_status' => 'required|in:تم باستاذان,خصم',
            'absence_count' => 'required|in:0,1,2',
        ]);

        $employee = EmployeeSubtraction::findOrFail($validated['id']);
        $employee->absence_status = $validated['absence_status'];
        $employee->absence_count = $validated['absence_count'];
        $employee->amount = $employee->amount * $validated['absence_count'];
        $employee->save();

        if ($validated['absence_status'] === 'تم باستاذان' && (int) $validated['absence_count'] === 0) {
            $date = Carbon::parse($employee->created_at)->format('Y-m-d');
            app(EmployeeAbsencePermissionService::class)->applyFullDayPermission(
                (int) $employee->employee_id,
                $date
            );
        }

        return response()->json($employee, 200);
    }

    public function employeesAbsences(Request $request)
    {
        $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 10;


        $search = EmployeeSubtraction::query()->where('type','غياب')->where('absence_status',null);

            if ($request->has('date')) {
                $search= $search->whereDate('created_at' ,$request->date);
            }

            if ($request->has('employee_id')) {
                $search= $search->where('employee_id' ,$request->employee_id);
            }

            if ($request->has('code')) {
                $search->whereHas('employee', function($q) use ($request) {
                    $q->where('code', 'like', '%' . $request->code . '%');
                });
            }


            $search= $search->with('employee');
            $search = $search->orderBy('id' , 'desc')->paginate($itemsPerPage);

        return response()->json($search, 200);
    }

    public function store(Request $request){

        $exist = EmployeeMonthPaid::where('employee_id' , $request->employee_id)
        ->where('month' , $request->month)
        ->where('year' , $request->year)
        ->first();

        if ($exist) {
            return response()->json(['message'=>'لا يمكن اضافة استقطاع لانه تم دفع الراتب لهذا الشهر'],422);
        }

        $request->validate([
            "type"=>"required",
            "amount"=>"required",
            "month"=>"required",
            "year"=>"required",
            "employee_id"=>"required"
        ]);

        $request['user_id'] = auth()->user()->id;

        $emplyee = EmployeeSubtraction::create($request->all());
        return response()->json($emplyee,201);
    }
}
