<?php

namespace App\Http\Controllers;

use App\Models\Approvals;
use App\Models\EmployeeFingerPrintSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeFingerPrintSheetController extends Controller
{
    public function update(Request $request){
        $data = $request->data;

        foreach ($data as $entry) {
            $record = EmployeeFingerPrintSheet::where('employee_id', $entry['employee_id'])
                                            ->where('date', $entry['date'])
                                            ->first();

            if ($record) {
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
                $record->save();
            }
        }

        return response()->json('success', 201);
    }


    public function reviewMonth(Request $request){
        $monthYear = explode('-', $request->month);
        $year = $monthYear[0];
        $month = $monthYear[1];
        EmployeeFingerPrintSheet::where('employee_id' ,$request->employee_id )->whereYear('date', $year)
        ->whereMonth('date', $month)
        ->update(['reviewed' => true]);


        return response()->json('success', 201);
    }

    public function absenceDeduction(Request $request){
        $data = $request->data;
        $row = EmployeeFingerPrintSheet::with('employee')->findOrFail($data['id']);
        if (auth()->user()->department != 'Admin') {
            $data['id']= $row->id;
            $appData = [
                'type' => 'update',
                'table_name' => 'employee_finger_print_sheets',
                'column_values' => $data,
                'details' => $row,
                'user_id' => auth()->user()->id,
            ];
            $approval = Approvals::create($appData);
            return response()->json($approval, 201);
        }
        $row->absence_deduction = $data['absence_deduction'];
        $row->save();
        return response()->json('success', 201);
    }

    public function addCheckOut(Request $request , $id){
        $data = $request->data;
        $row = EmployeeFingerPrintSheet::with('employee')->findOrFail($id);
        if (auth()->user()->department != 'Admin') {
            $data['id']= $row->id;
            $appData = [
                'type' => 'update',
                'table_name' => 'employee_finger_print_sheets',
                'column_values' => $data,
                'details' => $row,
                'user_id' => auth()->user()->id,
            ];
            $approval = Approvals::create($appData);
            return response()->json($approval, 201);
        }
        $row->check_out = $data['check_out'];
        $row->hours = $data['hours'];
        $row->time_out = $data['time_out'];
        $row->hours_permission = null;
        $row->save();

        return response()->json('success', 201);
    }

    public function editCheckInOrOut(Request $request , $id){
        $data = $request->data;
        $row = EmployeeFingerPrintSheet::with('employee')->findOrFail($id);
        if (auth()->user()->department != 'Admin') {
            $data['id']= $row->id;
            $appData = [
                'type' => 'update',
                'table_name' => 'employee_finger_print_sheets',
                'column_values' => $data,
                'details' => $row,
                'user_id' => auth()->user()->id,
            ];
            $approval = Approvals::create($appData);
            return response()->json($approval, 201);
        }
        $row->check_in = $data['check_in'];
        $row->time_in = $data['time_in'];
        $row->check_out = $data['check_out'];
        $row->time_out = $data['time_out'];
        $row->hours = $data['hours'];
        $row->hours_permission = null;
        $row->save();

        return response()->json('success', 201);
    }

    public function changeCheckIn(Request $request , $id){
        $data = $request->data;
        $row = EmployeeFingerPrintSheet::with('employee')->findOrFail($id);
        if (auth()->user()->department != 'Admin') {
            $data['id']= $row->id;
            $appData = [
                'type' => 'update',
                'table_name' => 'employee_finger_print_sheets',
                'column_values' => $data,
                'details' => $row,
                'user_id' => auth()->user()->id,
            ];
            $approval = Approvals::create($appData);
            return response()->json($approval, 201);
        }
        $row->check_in = $data['check_in'];
        $row->hours = $data['hours'];
        $row->time_in = $data['time_in'];
        $row->save();

        return response()->json($row, 201);
    }

}
