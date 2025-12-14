<?php

namespace App\Http\Controllers;

use App\Models\OrderSource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class OrderSourceController extends Controller
{
    //

    public function index()
    {
        $data = OrderSource::select('id', 'name')->get();
        return response()->json($data, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:order_sources,name',
        ]);

        OrderSource::create($request->all());
        return response()->json(['message' => 'تم اضافة مصدر الطلب بنجاح',]);
    }
}
