<?php

namespace App\Http\Controllers;

use App\Models\FactoryBankMovements;
use App\Models\FactoryBankMovementsCustody;
use Illuminate\Http\Request;
use App\Models\FactoryBankMovementsDetails;


class FactoryBankMovementsController extends Controller
{
    public function index(Request $request){
        $data = FactoryBankMovements::with(['user']);

        if ($request->has('month')) {
            $dateParts = explode('-', $request->month);
            $year = $dateParts[0];
            $month = $dateParts[1];

            $data = $data->whereYear('date', $year)
                        ->whereMonth('date', $month);
        }

        $data = $data->get();
        $lastRow = FactoryBankMovements::latest('id')->first();
        $FactoryBankMovementsDetails = FactoryBankMovementsDetails::with('user')->get();
        $FactoryBankMovementsCustody = FactoryBankMovementsCustody::with('user')->get();
        $response = [
            'data' => $data,
            'lastRow' => $lastRow,
            'FactoryBankMovementsDetails' => $FactoryBankMovementsDetails,
            'FactoryBankMovementsCustody' => $FactoryBankMovementsCustody,
        ];
        return response()->json($response, 200);
    }



    public function store(Request $request){
        if ($request->has('id')) {
            $row = FactoryBankMovements::find($request->id);
            $row->user_id = auth()->user()->id;
            if ($row->amount_in) {
                $row->balance = $row->balance - $row->amount_in + $request->amount;
                $row->amount_in = $request->amount;
                $row->save();
            } else {
                $row->balance = $row->balance + $row->amount_out - $request->amount;
                $row->amount_out = $request->amount;
                $row->save();
            }
            return response()->json($row, 200);
        }
        $request->validate([
            "description" => "required",
            "date" => "required|date",
        ]);

        $lastBalance = FactoryBankMovements::latest()->first()->balance ?? 0;

        $amount = $request->amount_in ?? -$request->amount_out;

        $newBalance = $lastBalance + $amount;

        $factoryBankMovements = FactoryBankMovements::create([
            "description" => $request->description,
            "date" => $request->date,
            "amount_in" => $request->amount_in ?? null,
            "amount_out" => $request->amount_out ?? null,
            "balance" => $newBalance,
            "user_id" => auth()->user()->id
        ]);

        return response()->json($factoryBankMovements, 201);
    }

    public function destroy($id)
    {
        $data = FactoryBankMovements::find($id);
        if(!$data){
        return response()->json(['error' => 'Not Found'], 404);
        }
        $data->delete();
        return response()->json('deleted sucuessfully');
    }

}
