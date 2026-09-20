<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeAdvancePayment;
use App\Models\EmployeeFingerPrintSheet;
use App\Models\EmployeeMerits;
use App\Models\Approvals;
use App\Models\EmployeeMonthAccrual;
use App\Models\EmployeeMonthPaid;
use App\Models\EmployeeSubtraction;
use App\Services\Accounting\AccountLinkingService;
use App\Services\Hr\EmployeeFingerPrintSheetAuditService;
use App\Services\Hr\EmployeeFingerPrintSheetNotificationService;
use App\Services\Hr\EmployeeAbsencePermissionService;
use App\Services\Hr\EmployeeFingerPrintSheetResolverService;
use App\Services\Hr\FridayExtraDayMeritService;
use App\Services\Hr\FingerprintHoursHelper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class EmployeeController extends Controller
{
    public function index(){
        $data = Employee::query()
            ->select(['id', 'name', 'code', 'acc_no', 'fixed_salary', 'working_hours', 'department', 'level'])
            ->orderBy('code', 'asc')
            ->get();

        return response()->json($data, 200);
    }

    public function linkPayableAccounts(AccountLinkingService $accountLinkingService)
    {
        $result = $accountLinkingService->linkAllUnlinkedEmployees();

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function store(Request $request){
        // حساب الشجرة اختياري — حوّل القيم الفارغة إلى null قبل التحقق
        if (! $request->filled('payable_tree_account_id')) {
            $request->merge(['payable_tree_account_id' => null]);
        }

        $request->validate([
            "name"=>"required",
            "code"=>"unique:employees",
            "level"=>"required",
            "department"=>"required",
            "fixed_salary"=>"required|numeric",
            "salary_type"=>"required",
            "payable_tree_account_id" => "nullable|integer|exists:tree_accounts,id",
        ]);

        if ($request->code =='' || $request->code == 0 || $request->code) {
            $last = Employee::orderBy('code', 'desc')->first();
            $request['code']= $last->code+1;
        }
        $emplyee = Employee::create($request->all());

        return response()->json($emplyee,201);
    }

    public function show($id)
    {
        $Employee = Employee::find($id);

        if (!$Employee) {
            return response()->json(['message' => 'not found'], 404);
        }

        return response()->json($Employee, 200);
    }

    public function edit($id , Request $request)
    {
        $request->validate([
            "name" => "required",
            "code" => "required|unique:employees,code," . $id,
            "level" => "required",
            "department" => "required",
            "fixed_salary" => "required|numeric",
            "salary_type" => "required",
            "payable_tree_account_id" => "nullable|integer|exists:tree_accounts,id",
        ]);

        // Find the employee by ID
        $employee = Employee::find($id);

        if (!$employee) {
            return response()->json(['message' => 'not found'], 404);
        }
        $employee->update($request->all());

        return response()->json($employee, 200);
    }

    public function updatePayableAccount(int $id, Request $request)
    {
        $request->validate([
            'payable_tree_account_id' => 'nullable|integer|exists:tree_accounts,id',
        ]);

        $employee = Employee::find($id);
        if (! $employee) {
            return response()->json(['message' => 'not found'], 404);
        }

        $employee->payable_tree_account_id = $request->input('payable_tree_account_id');
        $employee->save();

        return response()->json($employee->load('payableTreeAccount'), 200);
    }

    public function employeePerMonth($id, Request $request)
    {
        $month = $request->input("month");
        $year = $request->input("year");

        if (!$month || !$year) {
            return response()->json(["error" => "Please provide both month and year"], 400);
        }

        $employee = Employee::query()->where('id', '=', $id)
        ->with([
            'merits' => function ($query) use ($month, $year) {
                $query->where('month', $month)->where('year', $year)->with('user');
            },
            'subtraction' => function ($query) use ($month, $year) {
                $query->where('month', $month)->where('year', $year)
                    ->where(function ($query) {
                        $query->where('type', '!=', 'غياب')
                            ->orWhere(function ($query) {
                                $query->where('type', 'غياب')->whereNotNull('absence_status');
                            });
                    })->with('user');
            },
            'advance_payment' => function ($query) use ($month, $year) {
                $query->where('month', $month)->where('year', $year)->with('user');
            },
        ])
        ->first();



        return response()->json($employee, 200);
    }

    public function employeesPerMonth(Request $request)
    {
        $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 10;
        $month = $request->input("month");
        $year = $request->input("year");

        if (!$month || !$year) {
            return response()->json(["error" => "Please provide both month and year"], 400);
        }

        $search = Employee::query()
            ->select(['id', 'name', 'code', 'level', 'fixed_salary', 'salary_type', 'working_hours']);
            $this->applyEmployeeSearchFilters($search, $request);
            $search= $search->with([
                'fingerPrint' => function($query) use ($request) {
                    $this->constrainFingerPrintToPeriod($query, $request);
                    $query->select($this->fingerPrintPayrollColumns());
                },
                'merits' => function ($query) use ($month, $year) {
                    $query->where('month', $month)->where('year', $year)
                        ->select(['id', 'employee_id', 'type', 'amount', 'reason', 'month', 'year']);
                },
                'subtraction' => function ($query) use ($month, $year) {
                    $query->where('month', $month)->where('year', $year)
                        ->where(function ($query) {
                            $query->where('type', '!=', 'غياب')
                                ->orWhere(function ($query) {
                                    $query->where('type', 'غياب')->whereNotNull('absence_status');
                                });
                        })
                        ->select(['id', 'employee_id', 'type', 'amount', 'reason', 'month', 'year', 'absence_status']);
                },
                'advance_payment' => function ($query) use ($month, $year) {
                    $query->where('month', $month)->where('year', $year)
                        ->select(['id', 'employee_id', 'type', 'amount', 'reason', 'month', 'year']);
                },
                'salaryPaid' => function ($query) use ($month, $year) {
                    $query->where('month', $month)->where('year', $year)
                        ->select(['id', 'employee_id', 'month', 'year']);
                },
                'monthAccruals' => function ($query) use ($month, $year) {
                    $query->where('month', $month)->where('year', $year)
                        ->select(['id', 'employee_id', 'month', 'year', 'amount']);
                },
            ]);
            $search = $search->orderBy('code' , 'asc')->paginate($itemsPerPage);

        return response()->json($search, 200);
    }

    public function accountStatment(Request $request)
    {
        $meritsName = Schema::hasColumn('employee_merits', 'employee_name')
            ? 'COALESCE(employee.name, employee_merits.employee_name)'
            : 'employee.name';
        $subName = Schema::hasColumn('employee_subtractions', 'employee_name')
            ? 'COALESCE(employee.name, employee_subtractions.employee_name)'
            : 'employee.name';
        $advName = Schema::hasColumn('employee_advance_payments', 'employee_name')
            ? 'COALESCE(employee.name, employee_advance_payments.employee_name)'
            : 'employee.name';

        $employeeMerits = EmployeeMerits::select(
                'employee_merits.employee_id',
                DB::raw($meritsName . ' as employee_name'),
                'employee.code as employee_code',
                'employee.fixed_salary as fixed_salary',
                'type',
                'month',
                'year',
                'amount',
                'employee_merits.created_at',
                'employee_merits.reviewed',
                'employee_merits.id'
            )
            ->leftJoin('employees as employee', 'employee_merits.employee_id', '=', 'employee.id')
            ->whereDate('employee_merits.created_at', '=', $request->date);

        $employeeSubtraction = EmployeeSubtraction::select(
                'employee_subtractions.employee_id',
                DB::raw($subName . ' as employee_name'),
                'employee.code as employee_code',
                'employee.fixed_salary as fixed_salary',
                'type',
                'month',
                'year',
                'amount',
                'employee_subtractions.created_at',
                'employee_subtractions.reviewed',
                'employee_subtractions.id'
            )
            ->leftJoin('employees as employee', 'employee_subtractions.employee_id', '=', 'employee.id')
            ->whereDate('employee_subtractions.created_at', '=', $request->date)
            ->where(function ($query) {
                $query->where('type', '!=', 'غياب')
                    ->orWhere(function ($query) {
                        $query->where('type', 'غياب')->whereNotNull('absence_status');
                    });
            });

        $employeeAdvancePayment = EmployeeAdvancePayment::select(
                'employee_advance_payments.employee_id',
                DB::raw($advName . ' as employee_name'),
                'employee.code as employee_code',
                'employee.fixed_salary as fixed_salary',
                'type',
                'month',
                'year',
                'amount',
                'employee_advance_payments.created_at',
                'employee_advance_payments.reviewed',
                'employee_advance_payments.id'
            )
            ->leftJoin('employees as employee', 'employee_advance_payments.employee_id', '=', 'employee.id')
            ->whereDate('employee_advance_payments.created_at', '=', $request->date);

        $result = $employeeMerits
            ->union($employeeSubtraction)
            ->union($employeeAdvancePayment)
            ->orderBy('created_at','asc')
            ->get();

        return response()->json($result, 200);
    }

    public function reviewedStatus($id , Request $request)
    {

        if ($request->type =='غياب' || $request->type =='خصومات') {
            $emp = EmployeeSubtraction::find($id);
            if ($request->value =='true') {
                $emp->reviewed = 1;
            } else{
                $emp->reviewed = 0;
            }
            $emp->save();
        }

        if ($request->type =='سلف' ) {
            $emp = EmployeeAdvancePayment::find($id);
            if ($request->value =='true') {
                $emp->reviewed = 1;
            } else{
                $emp->reviewed = 0;
            }
            $emp->save();
        }

        if ($request->type =='الراتب المتغير' || $request->type =='حوافز' || $request->type =='مكافئات' || $request->type =='بدلات') {
            $emp = EmployeeMerits::find($id);
            if ($request->value =='true') {
                $emp->reviewed = 1;
            } else{
                $emp->reviewed = 0;
            }
            $emp->save();
        }


        return response()->json( 'success', 200);
    }

    public function search(Request $request){

        $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 10;
        $search = Employee::query();
        $this->applyEmployeeSearchFilters($search, $request);
        $search = $search->select([
                'id', 'name', 'code', 'department', 'level', 'working_hours',
                'acc_no', 'created_at', 'payable_tree_account_id',
            ])
            ->with('payableTreeAccount:id,name,code')
            ->orderBy('code' , 'asc')->paginate($itemsPerPage);
        return response()->json($search, 200);
    }

    public function destroy($id)
    {
        $employee = Employee::find($id);
        if (! $employee) {
            return response()->json(['message' => 'الموظف غير موجود', 'error' => 'Not Found'], 404);
        }

        try {
            DB::transaction(function () use ($employee) {
                $employeeId = (int) $employee->id;
                $employeeName = trim((string) ($employee->name ?? ''));
                if ($employeeName === '') {
                    $employeeName = 'موظف #' . $employeeId;
                }

                // احتفظ بسجلات المرتبات/السلف/الخصومات/الاستحقاقات باسم الموظف
                $retainTables = [
                    'employee_merits',
                    'employee_subtractions',
                    'employee_advance_payments',
                    'employee_month_paids',
                    'employee_month_accruals',
                    'employee_extra_hours',
                ];

                foreach ($retainTables as $tableName) {
                    if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'employee_id')) {
                        continue;
                    }

                    $update = [];
                    if (Schema::hasColumn($tableName, 'employee_name')) {
                        $update['employee_name'] = $employeeName;
                    }

                    if ($update !== []) {
                        DB::table($tableName)->where('employee_id', $employeeId)->update($update);
                    }

                    // افصل السجل عن الموظف حتى يمكن حذف صف الموظفين دون فقدان التاريخ
                    DB::table($tableName)->where('employee_id', $employeeId)->update(['employee_id' => null]);
                }

                // احذف البصمة فقط (مع سجلاتها)
                $sheetIds = EmployeeFingerPrintSheet::query()
                    ->where('employee_id', $employeeId)
                    ->pluck('id');

                if ($sheetIds->isNotEmpty() && Schema::hasTable('employee_finger_print_sheet_logs')) {
                    DB::table('employee_finger_print_sheet_logs')
                        ->whereIn('finger_print_sheet_id', $sheetIds)
                        ->delete();
                }

                EmployeeFingerPrintSheet::query()->where('employee_id', $employeeId)->delete();

                if (Schema::hasTable('cost_centers') && Schema::hasColumn('cost_centers', 'responsible_person_id')) {
                    DB::table('cost_centers')
                        ->where('responsible_person_id', $employeeId)
                        ->update(['responsible_person_id' => null]);
                }

                $employee->delete();
            });
        } catch (QueryException $e) {
            $sqlState = $e->errorInfo[0] ?? '';
            $isFkBlock = $sqlState === '23000'
                || str_contains($e->getMessage(), 'Integrity constraint violation')
                || str_contains($e->getMessage(), '1451');

            if ($isFkBlock) {
                Log::warning('employee_delete_blocked_by_fk', [
                    'employee_id' => $id,
                    'exception' => $e->getMessage(),
                ]);

                return response()->json([
                    'message' => 'لا يمكن حذف الموظف لوجود سجلات مرتبطة. شغّل ترحيل قاعدة البيانات ثم أعد المحاولة.',
                ], 422);
            }

            throw $e;
        }

        return response()->json('deleted sucuessfully');
    }

    public function saveExcelFingerPrintData(Request $request)
    {
        $data = $request->data;

        $year = (int) ($request->input('year') ?? 0);
        $month = (int) ($request->input('month') ?? 0);
        if ((! $year || ! $month) && ! empty($data[0]['date'])) {
            $payrollMonth = FingerprintHoursHelper::payrollMonthForDate((string) $data[0]['date']);
            $year = $payrollMonth['year'];
            $month = $payrollMonth['month'];
        }

        $period = FingerprintHoursHelper::resolvePeriod(
            $year,
            $month,
            $request->input('date_from'),
            $request->input('date_to')
        );

        if($request->has('status')){
            if($request->status == 'overwrite'){
                $records = EmployeeFingerPrintSheet::query()
                ->whereDate('date', '>=', $period['date_from'])
                ->whereDate('date', '<=', $period['date_to'])
                ->whereNotNull('updated_at')
                ->get();
                if ($records) {
                    EmployeeFingerPrintSheet::query()
                        ->whereDate('date', '>=', $period['date_from'])
                        ->whereDate('date', '<=', $period['date_to'])
                        ->whereNull('updated_at')
                        ->delete();
                    $data = array_filter($data, function($item) use ($records) {
                        foreach ($records as $elm) {
                            if ($item['employee_id'] == $elm['employee_id'] && $item['date'] == $elm['date']) {
                                return false;
                            }
                        }
                        return true;
                    });
                }
            }
            if($request->status == 'replace'){
                EmployeeFingerPrintSheet::query()
                    ->whereDate('date', '>=', $period['date_from'])
                    ->whereDate('date', '<=', $period['date_to'])
                    ->delete();
            }

        }

        try {
            $audit = app(EmployeeFingerPrintSheetAuditService::class);
            $newEntries = [];

            foreach ($data as $entry) {
                $existing = EmployeeFingerPrintSheet::where('employee_id', $entry['employee_id'])
                    ->where('date', $entry['date'])
                    ->first();

                if ($existing) {
                    $changes = $audit->diffModel($existing, $entry, ['check_in', 'check_out', 'hours']);
                    if ($changes !== []) {
                        $audit->log($existing->id, 'استيراد بصمة', $changes);
                    }
                } else {
                    $newEntries[] = $entry;
                }
            }

            DB::table('employee_finger_print_sheets')->upsert(
                $data,
                ['employee_id', 'date'],
                ['acc_no', 'check_in', 'check_out', 'hours', 'iso_date', 'time_in', 'time_out', 'times']
            );

            foreach ($newEntries as $entry) {
                $created = EmployeeFingerPrintSheet::where('employee_id', $entry['employee_id'])
                    ->where('date', $entry['date'])
                    ->first();

                if ($created) {
                    $audit->log($created->id, 'استيراد بصمة', [
                        'check_in' => ['label' => 'الحضور', 'old' => '—', 'new' => (string) ($entry['check_in'] ?? '—')],
                        'check_out' => ['label' => 'الانصراف', 'old' => '—', 'new' => (string) ($entry['check_out'] ?? '—')],
                        'hours' => ['label' => 'ساعات الحضور', 'old' => '—', 'new' => (string) ($entry['hours'] ?? '—')],
                    ]);
                }
            }

            $fridayEntries = array_values(array_filter($data, function ($entry) {
                return FingerprintHoursHelper::isFridayDate((string) ($entry['date'] ?? ''));
            }));
            if ($fridayEntries !== []) {
                app(FridayExtraDayMeritService::class)->syncForEntries($fridayEntries);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error saving data: ' . $e->getMessage()], 500);
        }
        return response()->json('success', 200);
    }

    public function getEmpsDataPerMonth(Request $request){
        $search = Employee::query()
            ->select(['id', 'name', 'acc_no', 'working_hours'])
            ->whereNotNull('acc_no')
            ->with(['fingerPrint' => function ($query) use ($request) {
                $this->constrainFingerPrintToPeriod($query, $request);
                $query->select([
                    'id',
                    'employee_id',
                    'date',
                    'hours',
                    'hours_permission',
                    'check_in',
                    'check_out',
                    'time_in',
                    'time_out',
                    'vacation',
                ]);
            }])
            ->orderBy('acc_no')
            ->get();

        return response()->json($search, 200);
    }

    public function getEmpDataPerMonth(Request $request ,$id){
        $monthYear = explode('-', (string) $request->month);
        $year = $monthYear[0] ?? null;
        $month = $monthYear[1] ?? null;

        $search = Employee::query()
            ->select(['id', 'name', 'code', 'acc_no', 'level', 'fixed_salary', 'working_hours', 'salary_type'])
            ->where('id', $id)
            ->with([
                'fingerPrint' => function ($query) use ($request) {
                    $query->select($this->fingerPrintDetailColumns());
                    $query->withCount('logs');
                    $this->constrainFingerPrintToPeriod($query, $request);

                    if ($request->has('filterDay')) {
                        switch ($request->filterDay) {
                            case 'late':
                                $query->where('hours', '<', $request->dayHours)->where('hours', '!=', '00:00');
                                break;

                            case 'overtime':
                                $query->where('hours', '>', $request->dayHours);
                                break;

                            case 'absent':
                                $query->where('hours', '00:00')->whereRaw('DAYOFWEEK(date) != 6');
                                break;

                            case 'all':
                                break;
                        }
                    }
                },
                'merits' => function ($query) use ($month, $year) {
                    if ($month && $year) {
                        $query->where('month', $month)->where('year', $year)->with('user:id,name');
                    }
                },
                'subtraction' => function ($query) use ($month, $year) {
                    if ($month && $year) {
                        $query->where('month', $month)->where('year', $year)
                            ->where(function ($query) {
                                $query->where('type', '!=', 'غياب')
                                    ->orWhere(function ($query) {
                                        $query->where('type', 'غياب')->whereNotNull('absence_status');
                                    });
                            })->with('user:id,name');
                    }
                },
                'advance_payment' => function ($query) use ($month, $year) {
                    if ($month && $year) {
                        $query->where('month', $month)->where('year', $year)->with('user:id,name');
                    }
                },
            ])
            ->first();

        if ($search && $search->relationLoaded('fingerPrint')) {
            $search->fingerPrint->each(fn ($sheet) => $sheet->unsetRelation('employee'));
        }

        return response()->json($search, 200);
    }

    public function empHoursPermission(Request $request){
        $validated = $request->validate([
            'data' => 'required|array',
            'data.hours_permission' => 'required|date_format:H:i',
            'data.check_in' => 'nullable|string|max:20',
            'data.check_out' => 'nullable|string|max:20',
            'data.hours' => 'nullable|string|max:10',
            'data.absence_deduction' => 'nullable',
            'data.id' => 'nullable|integer|exists:employee_finger_print_sheets,id',
            'data.employee_id' => 'required_without:data.id|integer|exists:employees,id',
            'data.date' => 'required_without:data.id|date',
        ]);

        $data = $validated['data'];
        $employee = app(EmployeeFingerPrintSheetResolverService::class)->resolve($data);
        $data['id'] = $employee->id;

        $sheetFields = ['hours_permission', 'check_in', 'check_out', 'hours', 'absence_deduction'];

        $audit = app(EmployeeFingerPrintSheetAuditService::class);
        $changes = $audit->diffModel($employee, $data, $sheetFields);
        foreach ($sheetFields as $field) {
            if (array_key_exists($field, $data)) {
                $employee->{$field} = $data[$field];
            }
        }
        $employee->reviewed = false;
        $employee->save();
        $audit->log($employee->id, 'إذن', $changes);
        app(EmployeeFingerPrintSheetNotificationService::class)->notifyAdmins(
            $employee,
            $this->hoursPermissionActionLabel($employee, $data['hours_permission'] ?? null)
        );

        return response()->json(['message' => 'Record updated successfully']);
    }

    public function revertAbsenceDayPermission(Request $request)
    {
        $validated = $request->validate([
            'data' => 'required|array',
            'data.id' => 'nullable|integer|exists:employee_finger_print_sheets,id',
            'data.employee_id' => 'required_without:data.id|integer|exists:employees,id',
            'data.date' => 'required_without:data.id|date',
        ]);

        $sheet = app(EmployeeAbsencePermissionService::class)->revertFullDayPermission($validated['data']);

        return response()->json([
            'message' => 'تم التراجع عن الإجازة بإذن',
            'id' => $sheet->id,
        ]);
    }

    public function empHoursPermissionAll(Request $request){
        $request->validate([
            'data' => 'required|array',
            'data.*.hours_permission' => 'required',
            'data.*.id' => 'nullable|integer|exists:employee_finger_print_sheets,id',
            'data.*.employee_id' => 'nullable|integer|exists:employees,id',
            'data.*.date' => 'nullable|date',
        ]);

        $data = $request->data;

        DB::beginTransaction();

        try {
            $audit = app(EmployeeFingerPrintSheetAuditService::class);
            $resolver = app(EmployeeFingerPrintSheetResolverService::class);

            foreach ($data as $item) {
                $employee = $resolver->resolve($item);
                $item['id'] = $employee->id;
                if ($employee) {
                    if (array_key_exists('is_overTime_removed', $item) && $item['is_overTime_removed']) {
                        if (auth()->user()->department != 'Admin') {
                            $appData = [
                                'type' => 'update',
                                'table_name' => 'employee_finger_print_sheets',
                                'column_values' => $item,
                                'details' => $employee,
                                'user_id' => auth()->user()->id,
                            ];
                            Approvals::create($appData);
                            $audit->log(
                                $employee->id,
                                'طلب تعديل',
                                $audit->diffModel($employee, $item, ['is_overTime_removed', 'hours_permission']),
                                'إزالة إضافي — في انتظار الموافقة'
                            );
                            continue;
                        }

                        $changes = $audit->diffModel($employee, $item, ['is_overTime_removed', 'hours_permission']);
                        $employee->is_overTime_removed = true;
                        if (array_key_exists('hours_permission', $item)) {
                            $employee->hours_permission = $item['hours_permission'];
                        }
                        $employee->reviewed = false;
                        $employee->save();
                        $audit->log($employee->id, 'إزالة إضافي', $changes);
                    } else {
                        $changes = $audit->diffModel($employee, $item, ['hours_permission', 'check_in', 'check_out', 'hours']);
                        foreach (['hours_permission', 'check_in', 'check_out', 'hours'] as $field) {
                            if (array_key_exists($field, $item)) {
                                $employee->{$field} = $item[$field];
                            }
                        }
                        $employee->reviewed = false;
                        $employee->save();
                        $audit->log($employee->id, 'إذن', $changes);
                        app(EmployeeFingerPrintSheetNotificationService::class)->notifyAdmins(
                            $employee,
                            $this->hoursPermissionActionLabel($employee, $item['hours_permission'] ?? null)
                        );
                    }
                }
            }

            DB::commit();
            return response()->json(['message' => 'Records updated successfully']);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => 'Failed to update records', 'message' => $e->getMessage()], 500);
        }
    }

    private function hoursPermissionActionLabel(EmployeeFingerPrintSheet $sheet, ?string $hoursPermission): string
    {
        $sheet->loadMissing('employee');
        $dayHours = (int) ($sheet->employee->working_hours ?: 8);

        return FingerprintHoursHelper::isFullDayPermission($hoursPermission, $dayHours)
            ? 'إجازة بإذن'
            : 'إذن';
    }

    private function applyEmployeeSearchFilters($query, Request $request): void
    {
        $name = trim((string) $request->input('name', ''));
        if ($name !== '') {
            $query->where('name', 'like', '%'.$name.'%');
        }

        $code = trim((string) $request->input('code', ''));
        if ($code !== '') {
            $query->where('code', 'like', $code.'%');
        }

        $type = trim((string) $request->input('type', ''));
        if ($type !== '' && $type !== 'نوع الراتب') {
            $query->where('salary_type', $type);
        }
    }

    /** أعمدة كشف المرتبات — بدون عمود times الثقيل؛ الحساب يعتمد على hours/check_in/time_in */
    private function fingerPrintPayrollColumns(): array
    {
        $columns = [
            'id',
            'employee_id',
            'date',
            'check_in',
            'check_out',
            'hours',
            'hours_permission',
            'time_in',
            'time_out',
            'vacation',
            'reviewed',
            'is_overTime_removed',
        ];
        if (Schema::hasColumn('employee_finger_print_sheets', 'absence_deduction')) {
            $columns[] = 'absence_deduction';
        }

        return $columns;
    }

    /** أعمدة تفاصيل البصمة — تشمل times لعرض كل الضربات */
    private function fingerPrintDetailColumns(): array
    {
        $columns = $this->fingerPrintPayrollColumns();
        $columns[] = 'iso_date';
        $columns[] = 'vacation_reason';
        $columns[] = 'acc_no';
        if (Schema::hasColumn('employee_finger_print_sheets', 'times')) {
            $columns[] = 'times';
        }

        return $columns;
    }

    private function constrainFingerPrintToPeriod($query, Request $request): void
    {
        $year = (int) $request->input('year');
        $month = (int) $request->input('month');
        if ($request->filled('month') && str_contains((string) $request->month, '-')) {
            $parts = explode('-', (string) $request->month);
            $year = (int) ($parts[0] ?? 0);
            $month = (int) ($parts[1] ?? 0);
        }
        if (! $year || ! $month) {
            return;
        }

        $period = FingerprintHoursHelper::resolvePeriod(
            $year,
            $month,
            $request->input('date_from'),
            $request->input('date_to')
        );
        FingerprintHoursHelper::applyDateRange($query, $period['date_from'], $period['date_to']);
    }
}
