<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \App\Models\IncomeList;

class IncomeListController extends Controller
{

    public function index(Request $request){
        $data = IncomeList::where('month' , $request->month)->first();
        return response()->json($data, 200);
    }

    public function store(Request $request)
    {
        $month = $request->input('month');

        $data = $request->except('month');

        if (count($data) !== 1) {
            return response()->json(['error' => 'Only one column value must be sent besides month'], 400);
        }

        $column = array_key_first($data);
        $value = $data[$column];

        // Create or update the record
        $incomeList = IncomeList::updateOrCreate(
            ['month' => $month],
            [$column => $value]
        );

        return response()->json(['message' => 'Success', 'data' => $incomeList], 201);
    }


}
