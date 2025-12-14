<?php

namespace App\Http\Controllers;

use App\Models\Income;
use Illuminate\Http\Request;

class IncomeController extends Controller
{
    public function index(){
        $incomes = Income::all();
        return response()->json($incomes);
    }

    public function store(Request $request){
        $request->validate([
            "type"=>"required",
            'date' => 'required|date',
            "income_amount"=>"required|numeric",
        ]);
        $income = Income::create($request->all());

        return response()->json($income,201);
    }
}
