<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Services\Items\ItemRecipeRevisionService;
use Illuminate\Http\Request;

class ItemRecipeRevisionController extends Controller
{
    public function rollForward(Request $request, int $id, ItemRecipeRevisionService $service)
    {
        $request->validate([
            'new_item_code' => 'sometimes|nullable|string|max:64',
            'clone_recipe' => 'sometimes|boolean',
            'duplicate_manufacture' => 'sometimes|boolean',
        ]);

        $item = Item::query()->find($id);
        if (! $item) {
            return response()->json(['message' => 'الصنف غير موجود'], 404);
        }

        try {
            $newItem = $service->rollForward($item, [
                'new_item_code' => $request->input('new_item_code'),
                'clone_recipe' => $request->boolean('clone_recipe', true),
                'duplicate_manufacture' => $request->boolean('duplicate_manufacture', true),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'تم إنشاء إصدار جديد من الصنف وتصفير مخزون النسخة السابقة.',
            'old_item_id' => (int) $id,
            'new_item' => $newItem,
        ], 201);
    }

    public function lineage(int $id, ItemRecipeRevisionService $service)
    {
        $versions = $service->lineageVersions($id);

        return response()->json([
            'versions' => $versions,
        ], 200);
    }
}
