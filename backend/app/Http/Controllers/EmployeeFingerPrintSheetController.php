<?php

namespace App\Http\Controllers;

use App\Models\EmployeeFingerPrintSheet;
use App\Services\Hr\EmployeeFingerPrintSheetAuditService;
use App\Services\Hr\EmployeeFingerPrintSheetNotificationService;
use App\Services\Hr\EmployeeFingerPrintSheetResolverService;
use App\Services\Hr\EmployeeSalaryAccrualService;
use App\Services\Hr\FingerprintHoursHelper;
use App\Services\Hr\FridayExtraDayMeritService;
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
                app(FridayExtraDayMeritService::class)->syncForSheet($record->fresh(['employee']));
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
        $period = FingerprintHoursHelper::resolvePeriod(
            $year,
            $month,
            $request->input('date_from'),
            $request->input('date_to')
        );

        EmployeeFingerPrintSheet::where('employee_id', $employeeId)
            ->whereDate('date', '>=', $period['date_from'])
            ->whereDate('date', '<=', $period['date_to'])
            ->update(['reviewed' => true]);

        $fridaySheets = EmployeeFingerPrintSheet::query()
            ->with('employee')
            ->where('employee_id', $employeeId)
            ->whereDate('date', '>=', $period['date_from'])
            ->whereDate('date', '<=', $period['date_to'])
            ->get()
            ->filter(fn ($sheet) => FingerprintHoursHelper::isFridayDate(
                $sheet->date instanceof \DateTimeInterface
                    ? $sheet->date->format('Y-m-d')
                    : (string) $sheet->date
            ));
        $fridaySync = app(FridayExtraDayMeritService::class);
        foreach ($fridaySheets as $sheet) {
            $fridaySync->syncForSheet($sheet);
        }

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
            'data.absence_deduction' => 'nullable|numeric|min:0',
            'data.id' => 'nullable|integer|exists:employee_finger_print_sheets,id',
            'data.employee_id' => 'required_without:data.id|integer|exists:employees,id',
            'data.date' => 'required_without:data.id|date',
        ]);

        $data = $validated['data'];
        $row = app(EmployeeFingerPrintSheetResolverService::class)->resolve($data);
        $data['id'] = $row->id;
        $actionLabel = $this->absenceDeductionLogTitle($data['absence_deduction'] ?? null);

        $changes = $this->audit->diffModel($row, $data, ['absence_deduction']);
        $row->absence_deduction = $data['absence_deduction'] ?? null;
        $row->reviewed = false;
        $row->save();
        $this->audit->log($row->id, $actionLabel, $changes);
        $this->notifier->notifyAdmins($row->fresh(['employee']), $actionLabel);

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
        app(FridayExtraDayMeritService::class)->syncForSheet($row->fresh(['employee']));

        return response()->json('success', 201);
    }

    /**
     * إلغاء يوم الغياب وتسجيل حضور/انصراف — ينشئ السجل إن لم يكن موجوداً.
     */
    public function registerAttendance(Request $request)
    {
        $validated = $request->validate([
            'data' => 'required|array',
            'data.id' => 'nullable|integer|exists:employee_finger_print_sheets,id',
            'data.employee_id' => 'required_without:data.id|integer|exists:employees,id',
            'data.date' => 'required_without:data.id|date',
            'data.check_in' => 'required|string',
            'data.check_out' => 'required|string',
            'data.hours' => 'required|string',
            'data.time_in' => 'required|string',
            'data.time_out' => 'required|string',
            'data.times' => 'nullable',
            'data.friday_reward_type' => 'nullable|in:extra_day,bonus,none',
            'data.friday_bonus_amount' => 'nullable|numeric|min:0',
            'data.friday_bonus_reason' => 'nullable|string|max:255',
        ]);

        $data = $validated['data'];
        $row = app(EmployeeFingerPrintSheetResolverService::class)->resolve($data);

        $payload = [
            'check_in' => $data['check_in'],
            'check_out' => $data['check_out'],
            'hours' => $data['hours'],
            'absence_deduction' => null,
            'hours_permission' => null,
        ];
        $changes = $this->audit->diffModel($row, $payload, [
            'check_in', 'check_out', 'hours', 'absence_deduction', 'hours_permission',
        ]);

        $row->check_in = $data['check_in'];
        $row->time_in = $data['time_in'];
        $row->check_out = $data['check_out'];
        $row->time_out = $data['time_out'];
        $row->hours = $data['hours'];
        $row->iso_date = $data['time_in'];
        $row->hours_permission = null;
        $row->absence_deduction = null;
        $row->reviewed = false;
        if (! empty($data['times'])) {
            $row->times = is_string($data['times']) ? $data['times'] : json_encode($data['times']);
        }
        $row->save();

        $this->audit->log($row->id, 'تسجيل حضور بعد غياب', $changes);
        $this->notifier->notifyAdmins($row, 'تسجيل حضور بعد غياب');

        $fridayOptions = [];
        if (! empty($data['friday_reward_type'])) {
            $fridayOptions['reward_type'] = $data['friday_reward_type'];
            if (($data['friday_reward_type'] ?? '') === 'bonus') {
                $fridayOptions['bonus_amount'] = $data['friday_bonus_amount'] ?? 0;
                if (! empty($data['friday_bonus_reason'])) {
                    $fridayOptions['bonus_reason'] = $data['friday_bonus_reason'];
                }
            }
        }
        app(FridayExtraDayMeritService::class)->syncForSheet($row->fresh(['employee']), $fridayOptions);

        return response()->json(['success' => true, 'id' => $row->id], 201);
    }

    public function editCheckInOrOut(Request $request, $id)
    {
        $data = $request->data;
        $row = EmployeeFingerPrintSheet::with('employee')->findOrFail($id);

        $changes = $this->audit->diffModel($row, array_merge($data, ['absence_deduction' => null]), [
            'check_in', 'check_out', 'hours', 'absence_deduction',
        ]);
        $row->check_in = $data['check_in'];
        $row->time_in = $data['time_in'];
        $row->check_out = $data['check_out'];
        $row->time_out = $data['time_out'];
        $row->hours = $data['hours'];
        $row->hours_permission = null;
        $row->absence_deduction = null;
        $row->reviewed = false;
        if (! empty($data['times'])) {
            $row->times = $data['times'];
        }
        $row->save();
        $this->audit->log($row->id, 'تعديل حضور/انصراف', $changes);
        $this->notifier->notifyAdmins($row, 'تعديل حضور/انصراف');
        app(FridayExtraDayMeritService::class)->syncForSheet($row->fresh(['employee']));

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
        app(FridayExtraDayMeritService::class)->syncForSheet($row->fresh(['employee']));

        return response()->json($row, 201);
    }

    private function absenceDeductionLogTitle(mixed $absenceDeduction): string
    {
        if ($absenceDeduction === null || $absenceDeduction === '') {
            return 'إلغاء مضاعفة الغياب';
        }

        if ((float) $absenceDeduction === 2.0) {
            return 'مضاعفة يوم الغياب';
        }

        return 'خصم بدون إذن';
    }
}
