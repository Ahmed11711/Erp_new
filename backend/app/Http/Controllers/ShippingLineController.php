<?php

namespace App\Http\Controllers;

use App\Models\shippingline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ShippingLineController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $data = shippingline::select('id', 'name')->get();
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
        $request->validate([
            'name' => 'required',
        ]);

        $line = shippingline::create([
            'name' => $request->name,
        ]);

        return response()->json($line, 200);
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
        $shippingline = shippingline::find($id);
        if (!$shippingline) {
            return response()->json('Shipping Line Not Found', 404);
        }
        $request->validate([
            'name' => 'required',
        ]);
        $shippingline->update([
            'name' => $request->name,
        ]);

        return response()->json('Shipping Line Updated Successfully', 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $shippingline = shippingline::find($id);
        if (!$shippingline) {
            return response()->json('Shipping Line Not Found', 404);
        }
        $shippingline->delete();

        return response()->json('Shipping Line Deleted Successfully', 200);
    }
}
