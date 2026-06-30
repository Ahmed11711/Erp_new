<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeAdvancePayment;
use App\Models\EmployeeFingerPrintSheet;
use App\Models\EmployeeMerits;
use App\Models\Approvals;
use App\Models\EmployeeSubtraction;
use App\Services\Hr\EmployeeFingerPrintSheetAuditService;
use App\Services\Hr\EmployeeFingerPrintSheetResolverService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EmployeeController extends Controller
{
    public function index(){
        $data = Employee::orderBy('code', 'asc')->get();
        return response()->json($data, 200);
    }

    public function store(Request $request){

        $request->validate([
            "name"=>"required",
            "code"=>"unique:employees",
            "level"=>"required",
            "department"=>"required",
            "fixed_salary"=>"required|numeric",
            "salary_type"=>"required",
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
        ]);

        // Find the employee by ID
        $employee = Employee::find($id);

        if (!$employee) {
            return response()->json(['message' => 'not found'], 404);
        }
        $employee->update($request->all());
        return response()->json($employee, 200);
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

        $search = Employee::query();
            if($request->has('name')){
                $search->where('name', 'like', '%'.$request->name.'%');
            }

            if($request->has('code')){
                $search->where('code', 'like',$request->code.'%');
            }
            if($request->has('type')){
                $search->where('salary_type', $request->type);
            }
            $search= $search->with([
                'fingerPrint' => function($query) use ($month, $year) {
                        $year = $year;
                        $month = $month;
                        $query->whereYear('date', $year)
                            ->whereMonth('date', $month);
                },
                'merits' => function ($query) use ($month, $year) {
                    $query->where('month', $month)->where('year', $year);
                },
                // 'subtraction' => function ($query) use ($month, $year) {
                //     $query->where('month', $month)->where('year', $year);
                // },
                'subtraction' => function ($query) use ($month, $year) {
                    $query->where('month', $month)->where('year', $year)
                        ->where(function ($query) {
                            $query->where('type', '!=', 'غياب')
                                ->orWhere(function ($query) {
                                    $query->where('type', 'غياب')->whereNotNull('absence_status');
                                });
                        });
                },
                'advance_payment' => function ($query) use ($month, $year) {
                    $query->where('month', $month)->where('year', $year);
                },
                'salaryPaid' => function ($query) use ($month, $year) {
                    $query->where('month', $month)->where('year', $year);
                },
            ]);
            $search = $search->orderBy('code' , 'asc')->paginate($itemsPerPage);

        return response()->json($search, 200);
    }

    public function accountStatment(Request $request)
    {

        $employeeMerits = EmployeeMerits::select('employee_merits.employee_id', 'employee.name as employee_name', 'employee.code as employee_code' , 'employee.fixed_salary as fixed_salary' ,  'type', 'month', 'year', 'amount', 'employee_merits.created_at' , 'employee_merits.reviewed' , 'employee_merits.id')
        ->join('employees as employee', 'employee_merits.employee_id', '=', 'employee.id')
        ->whereDate('employee_merits.created_at', '=', $request->date);

        // $employeeSubtraction = EmployeeSubtraction::select('employee_subtractions.employee_id', 'employee.name as employee_name', 'employee.code as employee_code' , 'employee.fixed_salary as fixed_salary', 'type', 'month', 'year', 'amount', 'employee_subtractions.created_at')
        //     ->join('employees as employee', 'employee_subtractions.employee_id', '=', 'employee.id')
        //     ->whereDate('employee_subtractions.created_at', '=', $request->date);

        $employeeSubtraction = EmployeeSubtraction::select('employee_subtractions.employee_id', 'employee.name as employee_name', 'employee.code as employee_code' , 'employee.fixed_salary as fixed_salary', 'type', 'month', 'year', 'amount', 'employee_subtractions.created_at', 'employee_subtractions.reviewed' , 'employee_subtractions.id')
            ->join('employees as employee', 'employee_subtractions.employee_id', '=', 'employee.id')
            ->whereDate('employee_subtractions.created_at', '=', $request->date)
            ->where(function ($query) {
                $query->where('type', '!=', 'غياب')
                    ->orWhere(function ($query) {
                        $query->where('type', 'غياب')->whereNotNull('absence_status');
                    });
            });


        $employeeAdvancePayment = EmployeeAdvancePayment::select('employee_advance_payments.employee_id', 'employee.name as employee_name', 'employee.code as employee_code' , 'employee.fixed_salary as fixed_salary', 'type', 'month', 'year', 'amount', 'employee_advance_payments.created_at', 'employee_advance_payments.reviewed' , 'employee_advance_payments.id')
            ->join('employees as employee', 'employee_advance_payments.employee_id', '=', 'employee.id')
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
        if($request->has('name')){
            $search->where('name', 'like', '%'.$request->name.'%');
        }

        if($request->has('code')){
            $search->where('code', 'like',$request->code.'%');
        }
        $search = $search->orderBy('code' , 'asc')->paginate($itemsPerPage);
        return response()->json($search, 200);
    }

    public function destroy($id)
    {
        $data = Employee::find($id);
        if(!$data){
        return response()->json(['error' => 'Not Found'], 404);
        }
        $data->delete();
        return response()->json('deleted sucuessfully');
    }

    public function saveExcelFingerPrintData(Request $request)
    {
        $data = $request->data;

        $dayMonthYear = explode('-', $data[0]['date']);
        $month = $dayMonthYear[1];
        $year = $dayMonthYear[0];


        if($request->has('status')){
            if($request->status == 'overwrite'){
                $records = EmployeeFingerPrintSheet::whereYear('date', $year)
                ->whereMonth('date', $month)
                ->whereNotNull('updated_at')
                ->get();
                if ($records) {
                    EmployeeFingerPrintSheet::whereYear('date', $year)->whereMonth('date', $month)->whereNull('updated_at')->delete();
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
                EmployeeFingerPrintSheet::whereYear('date', $year)->whereMonth('date', $month)->delete();
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
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error saving data: ' . $e->getMessage()], 500);
        }
        return response()->json('success', 200);
    }

    public function getEmpsDataPerMonth(Request $request){
        $search = Employee::query();

        $search->whereNotNull('acc_no')->with(['fingerPrint' => function($query) use ($request) {
            if ($request->has('month')) {
                $monthYear = explode('-', $request->month);
                $year = $monthYear[0];
                $month = $monthYear[1];
                $query->whereYear('date', $year)
                    ->whereMonth('date', $month);
            }
        }]);

        $search = $search->orderBy('acc_no')->get();

        return response()->json($search, 200);
    }

    public function getEmpDataPerMonth(Request $request ,$id){
        $search = Employee::query();

        $search->where('id',$id)->with(['fingerPrint' => function($query) use ($request) {
            $query->withCount('logs');
            if ($request->has('month')) {
                $monthYear = explode('-', $request->month);
                $year = $monthYear[0];
                $month = $monthYear[1];
                $query->whereYear('date', $year)
                    ->whereMonth('date', $month);
            }

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
        } , 'merits'  => function($query) use ($request) {
            $monthYear = explode('-', $request->month);
            $year = $monthYear[0];
            $month = $monthYear[1];
            $query->where('month',$month )->where('year' ,$year);
        }]);
        $search = $search->first();

        return response()->json($search, 200);
    }

    public function empHoursPermission(Request $request){
        $validated = $request->validate([
            'data' => 'required|array',
            'data.hours_permission' => 'required|date_format:H:i',
            'data.check_in' => 'nullable|string|max:20',
            'data.check_out' => 'nullable|string|max:20',
            'data.hours' => 'nullable|string|max:10',
            'data.id' => 'nullable|integer|exists:employee_finger_print_sheets,id',
            'data.employee_id' => 'required_without:data.id|integer|exists:employees,id',
            'data.date' => 'required_without:data.id|date',
        ]);

        $data = $validated['data'];
        $employee = app(EmployeeFingerPrintSheetResolverService::class)->resolve($data);
        $data['id'] = $employee->id;

        $sheetFields = ['hours_permission', 'check_in', 'check_out', 'hours'];

        if (auth()->user()->department != 'Admin') {
            $appData = [
                'type' => 'update',
                'table_name' => 'employee_finger_print_sheets',
                'column_values' => $data,
                'details' => $employee,
                'user_id' => auth()->user()->id,
            ];
            $approval = Approvals::create($appData);
            app(EmployeeFingerPrintSheetAuditService::class)->log(
                $employee->id,
                'طلب تعديل',
                app(EmployeeFingerPrintSheetAuditService::class)->diffModel($employee, $data, $sheetFields),
                'إذن — في انتظار الموافقة'
            );

            return response()->json($approval, 201);
        }

        $audit = app(EmployeeFingerPrintSheetAuditService::class);
        $changes = $audit->diffModel($employee, $data, $sheetFields);
        foreach ($sheetFields as $field) {
            if (array_key_exists($field, $data)) {
                $employee->{$field} = $data[$field];
            }
        }
        $employee->save();
        $audit->log($employee->id, 'إذن', $changes);

        return response()->json(['message' => 'Record updated successfully']);
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
                        $employee->save();
                        $audit->log($employee->id, 'إزالة إضافي', $changes);
                    } else {
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
                                $audit->diffModel($employee, $item, ['hours_permission', 'check_in', 'check_out', 'hours']),
                                'إذن — في انتظار الموافقة'
                            );
                            continue;
                        }

                        $changes = $audit->diffModel($employee, $item, ['hours_permission', 'check_in', 'check_out', 'hours']);
                        foreach (['hours_permission', 'check_in', 'check_out', 'hours'] as $field) {
                            if (array_key_exists($field, $item)) {
                                $employee->{$field} = $item[$field];
                            }
                        }
                        $employee->save();
                        $audit->log($employee->id, 'إذن', $changes);
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





}
