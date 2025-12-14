<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AssetController extends Controller
{
    public function index(){
        $data = Asset::all();
        return response()->json($data, 200);
    }

    public function store(Request $request){
        $request->validate([
            "name"=>"required",
            'asset_date' => 'required|date',
            "payment_amount"=>"required|numeric",
            "asset_amount"=>"required|numeric",
        ]);
        $asset = Asset::create($request->all());
        return response()->json($asset,201);
    }
}
