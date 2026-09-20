<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\EmployeeMerits;
use App\Models\EmployeeMonthPaid;
use App\Services\Hr\EmployeeFingerPrintSheetNotificationService;

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
        $this->notifyAttendanceDayBonus($emplyee, false);

        return response()->json($emplyee,201);
    }

    public function destroy($id)
    {
        $merit = EmployeeMerits::findOrFail($id);
        $this->notifyAttendanceDayBonus($merit, true);
        $merit->delete();

        return response()->json('deleted', 200);
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

    private function notifyAttendanceDayBonus(EmployeeMerits $merit, bool $removed): void
    {
        $label = $this->attendanceDayBonusLabel($merit->reason);
        if ($label === null) {
            return;
        }

        $employeeName = $merit->employee_name
            ?: optional(Employee::find($merit->employee_id))->name
            ?: 'موظف';
        $prefix = $removed ? 'إلغاء ' : '';
        app(EmployeeFingerPrintSheetNotificationService::class)->notifyAdminsAboutEmployee(
            $merit->employee_id ? (int) $merit->employee_id : null,
            sprintf('%s%s — %s', $prefix, $label, $employeeName)
        );
    }

    private function attendanceDayBonusLabel(?string $reason): ?string
    {
        if (! $reason) {
            return null;
        }
        if (preg_match('/^إضافي يوم كامل \((\d{4}-\d{2}-\d{2})\)$/u', $reason, $match)) {
            return 'مضاعفة يوم حضور — '.$match[1];
        }
        if (preg_match('/^مكافأة نصف يوم \((\d{4}-\d{2}-\d{2})\)$/u', $reason, $match)) {
            return 'مكافأة نصف يوم — '.$match[1];
        }

        return null;
    }
}
