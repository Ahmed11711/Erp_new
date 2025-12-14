<?php

namespace App\Http\Controllers;

use App\Models\ShippingMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ShippingMethodsController extends Controller
{
    //

    public function index()
    {
        $data = ShippingMethod::select('id', 'name')->get();
        return response()->json($data, 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:shipping_methods,name'
        ]);

        ShippingMethod::create($request->all());

        return response()->json(['message' => 'تم اضافة طريقة الشحن بنجاح',]);
    }
}
