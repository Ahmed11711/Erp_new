<?php

namespace App\Http\Controllers;

use App\Models\CorporateSalesLeadSource;
use Illuminate\Http\Request;

class CorporateSalesLeadSourceController extends Controller
{
    public function index(){
        $data = CorporateSalesLeadSource::get();
        return response()->json($data);
    }

    public function store(){

        CorporateSalesLeadSource::create([
            'name' => request('name'),
            'user_id' => auth()->user()->id
        ]);

        return response()->json(['message' => 'success'],201);
    }
}
