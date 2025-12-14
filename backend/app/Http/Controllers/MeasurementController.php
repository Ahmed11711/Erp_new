<?php

namespace App\Http\Controllers;

use App\Models\Measurement;
use Illuminate\Http\Request;
use Validator;
use Illuminate\Support\Facades\Cache;

class MeasurementController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $data = Measurement::all();
        return response()->json($data, 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
        $validation = Validator::make(request()->all(), [
            'unit' => 'required|string',
            'warehouse' => 'required|string',
        ])->validate();
        $measuremnt = Measurement::create([
            'unit' => request('unit'),
            'warehouse' => request('warehouse'),
        ]);

        return response()->json($measuremnt, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
        $measurement = Measurement::find($id);
        if(!$measurement){
         return response()->json('no id found');
        }
        $measurement->delete();

        return response()->json('deleted sucuessfully');
    }
}
