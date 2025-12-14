<?php

namespace App\Http\Controllers;

use App\Models\ShippingLineStatement;
use Illuminate\Http\Request;

class ShippingLineStatementController extends Controller
{
    public function index(Request $request){
        $data = ShippingLineStatement::select('id', 'date', 'shipping_company_id', 'order_id', 'user_id', 'canceled')
        ->where('date', $request->date)
        ->where('shipping_company_id', $request->company)
        ->with([
            'user:id,name',
            'order:id,customer_name,customer_phone_1,governorate,city,address,net_total',
            'shippingCompany:id,name'
        ])
        ->orderBy('id', 'desc')
        ->get();
            return response()->json($data, 200);
    }

    public function store(Request $request)
    {
        $details = $request;

        ShippingLineStatement::create([
            "date" => $details['date'],
            "order_id" => $details['order_id'],
            "shipping_company_id" => $details['company'],
            "user_id" => auth()->user()->id
        ]);


        return response()->json('success', 201);
    }



    public function destroy($id)
    {
        $data = ShippingLineStatement::find($id);
        if(!$data){
        return response()->json(['error' => 'Not Found'], 404);
        }
        $data->canceled = true;
        $data->save();
        return response()->json('canceled sucuessfully');
    }
}
