<?php

namespace App\Http\Controllers;

use App\Models\FactoryBankMovementsDetails;
use Illuminate\Http\Request;

class FactoryBankMovementsDetailsController extends Controller
{

    public function index(){
        $data = FactoryBankMovementsDetails::with('user')->get();
        return response()->json($data, 200);
    }

    public function store(Request $request)
    {
        $details = $request->data;

        foreach ($details as $od) {
            if (!empty($od['id'])) {
                $existingRecord = FactoryBankMovementsDetails::find($od['id']);

                if ($existingRecord) {
                    if (
                        $existingRecord->description !== $od['description'] ||
                        $existingRecord->amount != $od['amount']
                    ) {
                        $existingRecord->update([
                            "description" => $od['description'],
                            "amount" => $od['amount'],
                            "user_id" => auth()->user()->id
                        ]);
                    }
                }
            } else {
                FactoryBankMovementsDetails::create([
                    "description" => $od['description'],
                    "amount" => $od['amount'],
                    "user_id" => auth()->user()->id
                ]);
            }
        }

        return response()->json('success', 201);
    }



    public function destroy($id)
    {
        $data = FactoryBankMovementsDetails::find($id);
        if(!$data){
        return response()->json(['error' => 'Not Found'], 404);
        }
        $data->delete();
        return response()->json('deleted sucuessfully');
    }

}
