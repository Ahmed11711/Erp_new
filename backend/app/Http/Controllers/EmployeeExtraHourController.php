<?php

namespace App\Http\Controllers;

use App\Models\EmployeeExtraHour;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeExtraHourController extends Controller
{
    public function store(Request $request){

        $request->validate([
            "hours"=>"required",
            "month"=>"required",
            "year"=>"required",
            "employee_id"=>"required"
        ]);

        $request['user_id'] = auth()->user()->id;

        EmployeeExtraHour::create($request->all());

        $emplyees = Employee::orderBy('code', 'asc')->with(['extraHours'])->get();
        return response()->json($emplyees);
    }
}
