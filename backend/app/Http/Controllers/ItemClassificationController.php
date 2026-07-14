<?php

namespace App\Http\Controllers;

use App\Models\ItemClassification;
use Illuminate\Http\Request;
use Validator;

class ItemClassificationController extends Controller
{
    public function index()
    {
        $data = ItemClassification::query()->orderBy('warehouse')->orderBy('classification_name')->get();

        return response()->json($data, 200);
    }

    public function store(Request $request)
    {
        Validator::make($request->all(), [
            'classification_name' => 'required|string|max:128',
            'warehouse' => 'required|string',
        ])->validate();

        $row = ItemClassification::query()->create([
            'classification_name' => trim((string) $request->input('classification_name')),
            'warehouse' => trim((string) $request->input('warehouse')),
        ]);

        return response()->json($row, 201);
    }

    public function destroy($id)
    {
        $row = ItemClassification::query()->find($id);
        if (! $row) {
            return response()->json('no id found');
        }
        $row->delete();

        return response()->json('deleted sucuessfully');
    }
}
