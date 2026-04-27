<?php

namespace App\Http\Controllers;

use App\Services\Items\ParsedRecipeSheet;
use App\Services\Items\RecipeSheetImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Two-step interactive Excel import for recipes.
 *
 *   POST /api/recipes/import          — upload + parse + preview (returns import_token)
 *   POST /api/recipes/import/confirm  — confirm user decisions (create missing items,
 *                                        replace / create_new / skip per existing recipe)
 */
class RecipeImportController extends Controller
{
    private const CACHE_PREFIX = 'recipes.import.session.';
    private const CACHE_TTL_MINUTES = 30;

    public function __construct(
        private RecipeSheetImportService $service,
    ) {
    }

    /**
     * Step 1 — parse the uploaded Excel, detect missing items + existing recipes,
     * and cache the parsed payload under a short-lived token.
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:xlsx,xls,csv'],
            'sheet' => ['nullable', 'string', 'max:255'],
        ]);

        $uploaded = $request->file('file');
        $sheet = $request->input('sheet');

        // On many Linux hosts getRealPath() is false for php.ini upload_tmp_dir, or open_basedir
        // blocks /tmp. Moving into storage/app guarantees a path PhpSpreadsheet can open.
        $ext = strtolower((string) $uploaded->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            $ext = 'xlsx';
        }
        $workDir = storage_path('app'.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'recipe-import');
        if (! is_dir($workDir) && ! @mkdir($workDir, 0755, true) && ! is_dir($workDir)) {
            Log::error('Recipe import: cannot create work directory', ['dir' => $workDir]);

            return response()->json([
                'message' => 'تعذّر تجهيز مجلد مؤقت للاستيراد على الخادم.',
                'error' => 'mkdir_failed',
            ], 500);
        }
        $basename = (string) Str::uuid().'.'.$ext;
        $absolutePath = $workDir.DIRECTORY_SEPARATOR.$basename;
        $uploaded->move($workDir, $basename);

        try {
            $parsed = $this->service->parse($absolutePath, $sheet);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['file' => [$e->getMessage()]]);
        } catch (\Throwable $e) {
            Log::error('Recipe import preview failed', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return response()->json([
                'message' => 'تعذّر قراءة ملف الإكسيل. تأكد من الصيغة المطلوبة.',
                'error' => $e->getMessage(),
            ], 422);
        } finally {
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }

        $token = (string) Str::uuid();
        Cache::put(self::CACHE_PREFIX.$token, $parsed->toArray(), now()->addMinutes(self::CACHE_TTL_MINUTES));

        return response()->json([
            'import_token' => $token,
            'expires_in_minutes' => self::CACHE_TTL_MINUTES,
            'needs_missing_items_decision' => count($parsed->missingItems) > 0,
            'needs_conflicts_decision' => count($parsed->existingRecipes) > 0,
            'missing_items' => $parsed->missingItems,
            'existing_recipes' => $parsed->existingRecipes,
            'recipes' => array_map(function ($r) {
                return [
                    'recipe_name' => $r['recipe_name'],
                    'normalized_name' => $r['normalized_name'],
                    'exists' => $r['exists'],
                    'existing_recipe_id' => $r['existing_recipe_id'] ?? null,
                    'existing_recipe_name' => $r['existing_recipe_name'] ?? null,
                    'ingredients_count' => count($r['ingredients']),
                    'total_direct_cost' => $r['total_direct_cost'] ?? null,
                    'sell_price' => $r['sell_price'] ?? null,
                    'ingredients' => array_map(fn ($i) => [
                        'item_name' => $i['item_name'],
                        'normalized_name' => $i['normalized_name'],
                        'quantity' => $i['quantity'],
                        'unit' => $i['unit'],
                        'unit_cost' => $i['unit_cost'],
                        'line_cost' => $i['line_cost'] ?? null,
                        'item_exists' => $i['item_exists'],
                        'existing_item_id' => $i['existing_item_id'] ?? null,
                        'existing_item_name' => $i['existing_item_name'] ?? null,
                        'existing_item_price' => $i['existing_item_price'] ?? null,
                    ], $r['ingredients']),
                ];
            }, $parsed->recipes),
            'summary' => [
                'recipes_total' => count($parsed->recipes),
                'missing_items_total' => count($parsed->missingItems),
                'existing_recipes_total' => count($parsed->existingRecipes),
            ],
            'message' => $this->buildPreviewMessage($parsed),
        ]);
    }

    /**
     * Step 2 — apply user decisions and actually persist recipes / items / ingredients.
     *
     * Payload:
     *  {
     *    "import_token": "xxx",
     *    "create_missing_items": true,
     *    "recipe_actions": { "<normalized_name>": "replace" | "create_new" | "skip" }
     *  }
     */
    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'import_token' => ['required', 'string'],
            'create_missing_items' => ['required', 'boolean'],
            'recipe_actions' => ['nullable', 'array'],
            'recipe_actions.*' => ['nullable', 'in:replace,create_new,skip'],
        ]);

        $cacheKey = self::CACHE_PREFIX.$data['import_token'];
        $cached = Cache::get($cacheKey);

        if (! is_array($cached)) {
            return response()->json([
                'message' => 'انتهت صلاحية جلسة الاستيراد. يرجى رفع الملف مرة أخرى.',
            ], 410);
        }

        $parsed = ParsedRecipeSheet::fromArray($cached);

        if (! $data['create_missing_items'] && count($parsed->missingItems) > 0) {
            return response()->json([
                'message' => 'لا يمكن الاستيراد — توجد أصناف غير موجودة ولم يتم السماح بإنشائها.',
                'missing_items' => $parsed->missingItems,
            ], 422);
        }

        try {
            $result = $this->service->commit($parsed, [
                'create_missing_items' => (bool) $data['create_missing_items'],
                'recipe_actions' => $data['recipe_actions'] ?? [],
            ]);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['import' => [$e->getMessage()]]);
        } catch (\Throwable $e) {
            Log::error('Recipe import commit failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'تعذّر حفظ الوصفات: '.$e->getMessage(),
            ], 500);
        }

        Cache::forget($cacheKey);

        return response()->json([
            'message' => 'تم استيراد الوصفات بنجاح',
            'result' => $result->toArray(),
        ]);
    }

    /** Cancel a pending import session (optional cleanup endpoint). */
    public function cancel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'import_token' => ['required', 'string'],
        ]);
        Cache::forget(self::CACHE_PREFIX.$data['import_token']);

        return response()->json(['message' => 'تم إلغاء جلسة الاستيراد']);
    }

    private function buildPreviewMessage(ParsedRecipeSheet $parsed): string
    {
        if (count($parsed->missingItems) > 0 && count($parsed->existingRecipes) > 0) {
            return 'توجد أصناف غير موجودة ووصفات مكررة — يرجى اتخاذ القرار.';
        }
        if (count($parsed->missingItems) > 0) {
            return 'الأصناف التالية غير موجودة. هل تريد إنشاءها قبل المتابعة؟';
        }
        if (count($parsed->existingRecipes) > 0) {
            return 'بعض الوصفات موجودة بالفعل — اختر الإجراء المناسب لكل وصفة.';
        }

        return 'الملف جاهز للاستيراد.';
    }
}
