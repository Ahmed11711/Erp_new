<?php

namespace App\Http\Controllers;

use App\Models\Approvals;
use App\Models\EmployeeFingerPrintSheet;
use App\Services\Hr\EmployeeFingerPrintSheetAuditService;
use App\Services\Hr\EmployeeFingerPrintSheetNotificationService;
use App\Services\Hr\EmployeeFingerPrintSheetResolverService;
use App\Services\Hr\EmployeeSalaryAccrualService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeFingerPrintSheetController extends Controller
{
    public function __construct(
        private readonly EmployeeFingerPrintSheetAuditService $audit,
        private readonly EmployeeFingerPrintSheetNotificationService $notifier,
    ) {
    }

    public function logs(int $id)
    {
        $sheet = EmployeeFingerPrintSheet::findOrFail($id);

        $logs = $sheet->logs()
            ->orderByDesc('created_at')
            ->get();

        return response()->json($logs, 200);
    }

    public function update(Request $request)
    {
        $data = $request->data;

        foreach ($data as $entry) {
            $record = EmployeeFingerPrintSheet::where('employee_id', $entry['employee_id'])
                ->where('date', $entry['date'])
                ->first();

            if ($record) {
                $changes = $this->audit->diffModel($record, $entry, [
                    'check_in', 'check_out', 'hours', 'vacation', 'vacation_reason',
                ]);

                $record->check_in = $entry['check_in'];
                $record->check_out = $entry['check_out'];
                $record->hours = $entry['hours'];
                $record->iso_date = $entry['iso_date'];
                $record->time_in = $entry['time_in'];
                $record->time_out = $entry['time_out'];
                $record->vacation = $entry['vacation'];
                if (array_key_exists('vacation_reason', $entry)) {
                    $record->vacation_reason = $entry['vacation_reason'];
                }
                $record->reviewed = false;
                $record->save();

                $this->audit->log($record->id, 'تحديث كشف الحضور', $changes);
            }
        }

        return response()->json('success', 201);
    }

    public function reviewMonth(Request $request, EmployeeSalaryAccrualService $accrualService)
    {
        $monthYear = explode('-', $request->month);
        $year = (int) $monthYear[0];
        $month = (int) $monthYear[1];
        $employeeId = (int) $request->employee_id;

        EmployeeFingerPrintSheet::where('employee_id', $employeeId)->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->update(['reviewed' => true]);

        try {
            $accrual = DB::transaction(fn () => $accrualService->accrueForMonth($employeeId, $month, $year));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'accrual' => $accrual,
        ], 201);
    }

    public function absenceDeduction(Request $request)
    {
        $validated = $request->validate([
            'data' => 'required|array',
            'data.absence_deduction' => 'required',
            'data.id' => 'nullable|integer|exists:employee_finger_print_sheets,id',
            'data.employee_id' => 'required_without:data.id|integer|exists:employees,id',
            'data.date' => 'required_without:data.id|date',
        ]);

        $data = $validated['data'];
        $row = app(EmployeeFingerPrintSheetResolverService::class)->resolve($data);
        $data['id'] = $row->id;
        if (auth()->user()->department != 'Admin') {
            $data['id'] = $row->id;
            $appData = [
                'type' => 'update',
                'table_name' => 'employee_finger_print_sheets',
                'column_values' => $data,
                'details' => $row,
                'user_id' => auth()->user()->id,
            ];
            $approval = Approvals::create($appData);
            $this->audit->log(
                $row->id,
                'طلب تعديل',
                $this->audit->diffModel($row, $data, ['absence_deduction']),
                'خصم بدون إذن — في انتظار الموافقة'
            );

            return response()->json($approval, 201);
        }

        $changes = $this->audit->diffModel($row, $data, ['absence_deduction']);
        $row->absence_deduction = $data['absence_deduction'];
        $row->save();
        $this->audit->log($row->id, 'خصم بدون إذن', $changes);

        return response()->json('success', 201);
    }

    public function addCheckOut(Request $request, $id)
    {
        $data = $request->data;
        $row = EmployeeFingerPrintSheet::with('employee')->findOrFail($id);

        $changes = $this->audit->diffModel($row, $data, ['check_out', 'hours']);
        $row->check_out = $data['check_out'];
        $row->hours = $data['hours'];
        $row->time_out = $data['time_out'];
        $row->hours_permission = null;
        $row->reviewed = false;
        if (! empty($data['times'])) {
            $row->times = $data['times'];
        }
        $row->save();
        $this->audit->log($row->id, 'إضافة انصراف', $changes);
        $this->notifier->notifyAdmins($row, 'إضافة انصراف');

        return response()->json('success', 201);
    }

    public function editCheckInOrOut(Request $request, $id)
    {
        $data = $request->data;
        $row = EmployeeFingerPrintSheet::with('employee')->findOrFail($id);

        $changes = $this->audit->diffModel($row, $data, ['check_in', 'check_out', 'hours']);
        $row->check_in = $data['check_in'];
        $row->time_in = $data['time_in'];
        $row->check_out = $data['check_out'];
        $row->time_out = $data['time_out'];
        $row->hours = $data['hours'];
        $row->hours_permission = null;
        $row->reviewed = false;
        if (! empty($data['times'])) {
            $row->times = $data['times'];
        }
        $row->save();
        $this->audit->log($row->id, 'تعديل حضور/انصراف', $changes);
        $this->notifier->notifyAdmins($row, 'تعديل حضور/انصراف');

        return response()->json('success', 201);
    }

    public function changeCheckIn(Request $request, $id)
    {
        $data = $request->data;
        $row = EmployeeFingerPrintSheet::with('employee')->findOrFail($id);

        $changes = $this->audit->diffModel($row, $data, ['check_in', 'hours']);
        $row->check_in = $data['check_in'];
        $row->hours = $data['hours'];
        $row->time_in = $data['time_in'];
        $row->reviewed = false;
        if (! empty($data['times'])) {
            $row->times = $data['times'];
        }
        $row->save();
        $this->audit->log($row->id, 'تغيير وقت الحضور', $changes);
        $this->notifier->notifyAdmins($row, 'تغيير وقت الحضور');

        return response()->json($row, 201);
    }
}
