<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Employee;
use App\Models\EmployeeMonthPaid;
use App\Models\EmployeeSubtraction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class EmployeeMonthPaidController extends Controller
{
    public function store(Request $request){

        $request->validate([
            "amount"=>"required",
            "month"=>"required",
            "year"=>"required",
            "bank_id"=>"required",
            "employee_id"=>"required"
        ]);

        $exist = EmployeeMonthPaid::where('employee_id' , $request->employee_id)
            ->where('month' , $request->month)
            ->where('year' , $request->year)
            ->first();

        if ($exist) {
            return response()->json(['message'=>'تم دفع الراتب لهذا الشهر'],422);
        }

        $isAbsence = EmployeeSubtraction::where('employee_id' , $request->employee_id)->where('type' , 'غياب')->whereNull('absence_status')->first();

        if ($isAbsence) {
            return response()->json(['message'=>'يرجي مراجعة كشف الغياب لهذا الشهر'],422);
        }


        $request['user_id'] = auth()->user()->id;

        $emplyee = EmployeeMonthPaid::create($request->all());

        $emplyeeData  = Employee::find($emplyee->employee_id);

        $bank  = Bank::find($emplyee->bank_id);
        $balance =(double) $bank->balance;
        $bank->balance = $bank->balance - $emplyee->amount;
        $bank->save();


        DB::table('bank_details')->insert([
            'bank_id' => $emplyee->bank_id,
            'details' => ' صرف مرتب '.$emplyeeData->name.' بتاريخ '.date('Y-m-d').' عن شهر '.$request->month,
            'ref' => '-',
            'type' =>'صرف مرتب',
            'amount' => (double)$emplyee->amount,
            'balance_before' => $balance,
            'balance_after' => $bank->balance,
            'date' => date('Y-m-d'),
            'created_at' => now(),
            'user_id'=> auth()->user()->id
        ]);
        return response()->json($emplyee,201);
    }
}
