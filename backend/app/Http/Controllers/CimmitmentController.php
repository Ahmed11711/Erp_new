<?php

namespace App\Http\Controllers;

use App\Models\Cimmitment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CimmitmentController extends Controller
{
    public function index(){
        $data = Cimmitment::all();
        return response()->json($data, 200);
    }

    public function store(Request $request){
        $request->validate([
            "name"=>"required",
            'date' => 'required|date',
            "deserved_amount"=>"required|numeric",
        ]);
        $cimmitment = Cimmitment::create($request->all());
        return response()->json($cimmitment,201);
    }
}
