<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\EmployeeMerits;
use App\Models\EmployeeMonthPaid;

class EmployeeMeritsController extends Controller
{
    public function store(Request $request){

        $exist = EmployeeMonthPaid::where('employee_id' , $request->employee_id)
        ->where('month' , $request->month)
        ->where('year' , $request->year)
        ->first();

        if ($exist) {
            return response()->json(['message'=>'لا يمكن اضافة استحقاق لانه تم دفع الراتب لهذا الشهر'],422);
        }

        $request->validate([
            "type"=>"required",
            "amount"=>"required",
            "month"=>"required",
            "year"=>"required",
            "employee_id"=>"required"
        ]);

        $request['user_id'] = auth()->user()->id;

        $emplyee = EmployeeMerits::create($request->all());
        return response()->json($emplyee,201);
    }

    public function addFixedChangedSalary(Request $request){

        $exist = EmployeeMonthPaid::where('employee_id' , $request->employee_id)
        ->where('month' , $request->month)
        ->where('year' , $request->year)
        ->first();

        if ($exist) {
            return response()->json(['message'=>'لا يمكن تغيير الراتب لانه تم دفع الراتب لهذا الشهر'],422);
        }

        $request->validate([
            "type"=>"required",
            "amount"=>"required",
            "month"=>"required",
            "year"=>"required",
            "employee_id"=>"required"
        ]);

        $request['user_id'] = auth()->user()->id;
        $emplyee = EmployeeMerits::where('type', $request->type)
            ->where('month', $request->month)
            ->where('year', $request->year)
            ->where('employee_id', $request->employee_id)
            ->delete();
        if ($request->amount > 0) {
            $emplyee = EmployeeMerits::create($request->all());
        }
        return response()->json($emplyee,201);
    }
}
