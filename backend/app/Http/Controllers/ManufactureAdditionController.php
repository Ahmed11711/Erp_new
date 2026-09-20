<?php

namespace App\Http\Controllers;

use App\Models\ManufactureAddition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManufactureAdditionController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = ManufactureAddition::query()->orderBy('name')->get();

        return response()->json($rows, 200);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cost' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:100'],
        ]);

        $row = ManufactureAddition::create([
            'name' => trim($data['name']),
            'cost' => $data['cost'],
            'unit' => isset($data['unit']) ? trim((string) $data['unit']) : null,
        ]);

        return response()->json($row, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = ManufactureAddition::find($id);
        if (! $row) {
            return response()->json(['message' => 'الإضافة غير موجودة'], 404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cost' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:100'],
        ]);

        $row->update([
            'name' => trim($data['name']),
            'cost' => $data['cost'],
            'unit' => isset($data['unit']) ? trim((string) $data['unit']) : null,
        ]);

        return response()->json($row, 200);
    }

    public function destroy(int $id): JsonResponse
    {
        $row = ManufactureAddition::find($id);
        if (! $row) {
            return response()->json(['message' => 'الإضافة غير موجودة'], 404);
        }

        $row->delete();

        return response()->json(['message' => 'تم الحذف بنجاح'], 200);
    }
}
