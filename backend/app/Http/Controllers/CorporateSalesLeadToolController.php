<?php

namespace App\Http\Controllers;

use App\Models\CorporateSalesLeadTool;
use Illuminate\Http\Request;

class CorporateSalesLeadToolController extends Controller
{
    public function index(){
        $data = CorporateSalesLeadTool::get();
        return response()->json($data);
    }

    public function store(){

        CorporateSalesLeadTool::create([
            'name' => request('name'),
            'user_id' => auth()->user()->id
        ]);

        return response()->json(['message' => 'success'],201);
    }
}
