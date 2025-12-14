<?php

namespace App\Http\Controllers;

use App\Models\FactoryBankMovementsCustody;
use Illuminate\Http\Request;

class FactoryBankMovementsCustodyController extends Controller
{
    public function index(){
        $data = FactoryBankMovementsCustody::with('user')->get();
        return response()->json($data, 200);
    }

    public function store(Request $request)
    {
        $details = $request->data;

        foreach ($details as $od) {
            if (!empty($od['id'])) {
                $existingRecord = FactoryBankMovementsCustody::find($od['id']);

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
                FactoryBankMovementsCustody::create([
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
        $data = FactoryBankMovementsCustody::find($id);
        if(!$data){
        return response()->json(['error' => 'Not Found'], 404);
        }
        $data->delete();
        return response()->json('deleted sucuessfully');
    }

}
