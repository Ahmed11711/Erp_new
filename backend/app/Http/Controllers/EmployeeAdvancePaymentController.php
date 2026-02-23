<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Employee;
use App\Models\EmployeeAdvancePayment;
use App\Services\Accounting\EmployeePaymentAccountingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\EmployeeMonthPaid;

class EmployeeAdvancePaymentController extends Controller
{
    public function store(Request $request)
    {
        $exist = EmployeeMonthPaid::where('employee_id', $request->employee_id)
            ->where('month', $request->month)
            ->where('year', $request->year)
            ->first();

        if ($exist) {
            return response()->json(['message' => 'لا يمكن اضافة سلفه لانه تم دفع الراتب لهذا الشهر'], 422);
        }

        $request->validate([
            'type' => 'required',
            'amount' => 'required',
            'month' => 'required',
            'year' => 'required',
            'bank_id' => 'required',
            'employee_id' => 'required',
        ]);

        $request['user_id'] = auth()->user()->id;

        $emplyee = EmployeeAdvancePayment::create($request->all());
        $emplyeeData = Employee::find($emplyee->employee_id);

        $bank = Bank::find($emplyee->bank_id);
        $balance = (double) $bank->balance;
        $bank->balance = $bank->balance - $emplyee->amount;
        $bank->save();

        DB::table('bank_details')->insert([
            'bank_id' => $emplyee->bank_id,
            'details' => ' صرف سلف ' . $emplyeeData->name . ' بتاريخ ' . date('Y-m-d') . ' عن شهر ' . $request->month,
            'ref' => '-',
            'type' => 'صرف سلف',
            'amount' => (double) $emplyee->amount,
            'balance_before' => $balance,
            'balance_after' => $bank->balance,
            'date' => date('Y-m-d'),
            'created_at' => now(),
            'user_id' => auth()->user()->id,
        ]);

        // Accounting: daily entry (debit salary expense, credit bank) — appears in daily register
        app(EmployeePaymentAccountingService::class)->postPayment(
            'صرف سلف - ' . ($emplyeeData->name ?? 'موظف'),
            (float) $emplyee->amount,
            (int) $emplyee->bank_id
        );

        return response()->json($emplyee, 201);
    }
}
