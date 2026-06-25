<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\InventoryMovementType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Production;
use App\Models\Stock;
use App\Services\Accounting\InventoryGlPostingService;
use App\Services\Inventory\InventoryMovementLedgerService;
use App\Models\TreeAccount;
use App\Services\Items\RecipeSheetImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Excel workflows: master items, opening balances from physical count, adjustment batches.
 */
class InventoryExcelImportController extends Controller
{
    /** @var list<string> */
    private const STANDARD_WAREHOUSES = [
        'مخزن مواد خام',
        'مخزن منتج تحت التشغيل',
        'مخزن منتج تام',
        'مخزن صيانة',
        'مخزن تالف',
    ];

    private function resolveDefaultWarehouse(?string $requested, string $fallback = 'مخزن مواد خام'): string
    {
        $warehouse = trim((string) ($requested ?? ''));
        if ($warehouse === '') {
            return $fallback;
        }

        if (! in_array($warehouse, self::STANDARD_WAREHOUSES, true)) {
            throw new \InvalidArgumentException('المخزن المحدد غير معروف: '.$warehouse);
        }

        return $warehouse;
    }

    /**
     * أول سطر غير فارغ: يُفضّل TAB أو الفاصلة المنقوطة (Excel العربي) أو الفاصلة.
     */
    private function detectCsvDelimiter(string $utf8): string
    {
        $lines = preg_split("/\r\n|\r|\n/", $utf8);
        $first = '';
        foreach ($lines as $ln) {
            $first = (string) $ln;
            if (trim($first) !== '') {
                break;
            }
        }
        if ($first === '') {
            return ',';
        }
        $tabs = substr_count($first, "\t");
        $semi = substr_count($first, ';');
        $comma = substr_count($first, ',');

        if ($tabs >= 1 && $tabs >= $semi && $tabs >= $comma) {
            return "\t";
        }
        if ($semi >= 1 && $semi >= $comma) {
            return ';';
        }

        return ',';
    }

    /**
     * تحميل ملف Excel/CSV: UTF-8 أو UTF-16؛ CSV يُفصل تلقائياً بـ TAB أو ; أو ,.
     */
    private function loadSpreadsheet(string $path): Spreadsheet
    {
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($ext, ['csv', 'txt'], true)) {
            return IOFactory::load($path);
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return IOFactory::load($path);
        }

        if (str_starts_with($raw, "\xFF\xFE")) {
            $raw = substr($raw, 2);
            $utf8 = mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
        } elseif (str_starts_with($raw, "\xFE\xFF")) {
            $raw = substr($raw, 2);
            $utf8 = mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE');
        } elseif (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $utf8 = substr($raw, 3);
        } else {
            $utf8 = $raw;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'invcsv_');
        if ($tmp === false) {
            return IOFactory::load($path);
        }

        try {
            file_put_contents($tmp, $utf8);
            $delimiter = $this->detectCsvDelimiter($utf8);
            $reader = IOFactory::createReader('Csv');
            $reader->setDelimiter($delimiter);
            $reader->setEnclosure('"');

            return $reader->load($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    /** رقم خط الإنتاج من العمود أو مطابقة اسم فرع الإنتاج مع production_line. */
    private function resolveProductionIdFromLine(array $line, array $idx, int $fallback): int
    {
        $pidCol = $this->columnIndex($idx, ['production_id', 'رقم الانتاج']);
        if ($pidCol !== null && array_key_exists($pidCol, $line)) {
            $raw = trim((string) ($line[$pidCol] ?? ''));
            $parsed = filter_var($raw, FILTER_VALIDATE_INT);
            if ($parsed !== false && $parsed > 0 && Production::query()->whereKey($parsed)->exists()) {
                return $parsed;
            }
        }

        $branchCol = $this->columnIndex($idx, ['فرع الانتاج', 'خط الانتاج', 'production_line']);
        if ($branchCol !== null) {
            $label = trim((string) ($line[$branchCol] ?? ''));
            if ($label !== '') {
                $found = Production::query()->whereRaw('TRIM(production_line) = ?', [$label])->value('id');
                if ($found) {
                    return (int) $found;
                }
            }
        }

        return $fallback;
    }

    /**
     * حقول اختيارية من الصف إن وُجدت أعمدتها (كود، لون، فرع إنتاج، مرجع، حد أدنى، وحدة قياس).
     */
    private function applyOptionalCategoryFieldsFromRow(Category $cat, array $line, array $idx): void
    {
        $updates = [];

        $codeCol = $this->columnIndex($idx, ['item_code', 'sku', 'كود الصنف', 'الكود', 'الباركود']);
        if ($codeCol !== null) {
            $v = trim((string) ($line[$codeCol] ?? ''));
            if ($v !== '') {
                $updates['item_code'] = $v;
            }
        }

        $colorCol = $this->columnIndex($idx, ['color', 'لون', 'اللون']);
        if ($colorCol !== null) {
            $v = trim((string) ($line[$colorCol] ?? ''));
            if ($v !== '') {
                $updates['color'] = $v;
            }
        }

        $refCol = $this->columnIndex($idx, ['ref', 'المرجع']);
        if ($refCol !== null) {
            $v = trim((string) ($line[$refCol] ?? ''));
            if ($v !== '') {
                $updates['ref'] = $v;
            }
        }

        $minCol = $this->columnIndex($idx, ['minimum_quantity', 'الحد الأدنى للكمية', 'الحد الأدنى', 'الحد الادنى']);
        if ($minCol !== null && array_key_exists($minCol, $line) && $line[$minCol] !== null && $line[$minCol] !== '') {
            $updates['minimum_quantity'] = (float) $line[$minCol];
        }

        $measCol = $this->columnIndex($idx, ['measurement_id', 'رقم الوحدة', 'وحدة القياس']);
        if ($measCol !== null && array_key_exists($measCol, $line) && $line[$measCol] !== null && $line[$measCol] !== '') {
            $mid = (int) $line[$measCol];
            if ($mid > 0) {
                $updates['measurement_id'] = $mid;
            }
        }

        $newProd = $this->resolveProductionIdFromLine($line, $idx, (int) $cat->production_id);
        if ($newProd !== (int) $cat->production_id) {
            $updates['production_id'] = $newProd;
        }

        if ($updates !== []) {
            $cat->update($updates);
        }
    }

    private function headerRow(array $row): array
    {
        return array_map(fn ($c) => is_string($c) ? strtolower(trim($c)) : $c, $row);
    }

    /**
     * أول عمود يطابق أحد الأسماء (إنجليزي أو عربي). الرؤوس مُطبَّعة بـ headerRow().
     *
     * @param  array<string|int, int>  $idx  ناتج array_flip على صف الرؤوس
     */
    private function columnIndex(array $idx, array $candidates): ?int
    {
        foreach ($candidates as $c) {
            if (! is_string($c)) {
                continue;
            }
            $k = strtolower(trim($c));
            if ($k !== '' && isset($idx[$k])) {
                return $idx[$k];
            }
        }
        foreach ($candidates as $c) {
            if (! is_string($c)) {
                continue;
            }
            $k = trim($c);
            if ($k !== '' && isset($idx[$k])) {
                return $idx[$k];
            }
        }

        return null;
    }

    /**
     * Columns: name (or category_name), category_price, warehouse, production_id, measurement_id, item_code (optional), sku (optional alias item_code)
     */
    public function importItems(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt',
            'default_warehouse' => 'nullable|string|max:255',
        ]);

        try {
            $defaultWarehouse = $this->resolveDefaultWarehouse($request->input('default_warehouse'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $path = $request->file('file')->getRealPath();
        $sheet = $this->loadSpreadsheet($path)->getActiveSheet();
        $rows = $sheet->toArray();
        if (count($rows) < 2) {
            return response()->json(['message' => 'Empty sheet'], 422);
        }

        $headers = $this->headerRow($rows[0]);
        $idx = array_flip($headers);
        $created = 0;
        $updated = 0;

        $defaultProductionId = (int) (Production::query()->value('id') ?: 1);

        DB::beginTransaction();
        try {
            for ($r = 1; $r < count($rows); $r++) {
                $line = $rows[$r];
                $nameCol = $this->columnIndex($idx, ['name', 'category_name', 'اسم الصنف', 'الصنف', 'اسم']);
                $name = $nameCol !== null ? trim((string) ($line[$nameCol] ?? '')) : '';
                if ($name === '') {
                    continue;
                }
                $priceCol = $this->columnIndex($idx, ['category_price', 'price', 'سعر التكلفة', 'السعر', 'التكلفة']);
                $price = $priceCol !== null ? (float) ($line[$priceCol] ?? 0) : 0.0;
                $warehouseCol = $this->columnIndex($idx, ['warehouse', 'المخزن', 'مخزن']);
                $warehouse = $warehouseCol !== null ? trim((string) ($line[$warehouseCol] ?? '')) : '';
                if ($warehouse === '') {
                    $warehouse = $defaultWarehouse;
                }
                $productionId = $this->resolveProductionIdFromLine($line, $idx, $defaultProductionId);
                $measurementCol = $this->columnIndex($idx, ['measurement_id', 'وحدة القياس', 'رقم الوحدة']);
                $measurementId = $measurementCol !== null && isset($line[$measurementCol])
                    ? (int) $line[$measurementCol]
                    : 1;
                $skuCol = $this->columnIndex($idx, ['sku', 'item_code', 'كود الصنف', 'الباركود', 'الكود']);
                $sku = $skuCol !== null ? trim((string) ($line[$skuCol] ?? '')) : '';

                $colorCol = $this->columnIndex($idx, ['color', 'لون', 'اللون']);
                $colorVal = $colorCol !== null ? trim((string) ($line[$colorCol] ?? '')) : '';
                $refCol = $this->columnIndex($idx, ['ref', 'المرجع']);
                $refVal = $refCol !== null ? trim((string) ($line[$refCol] ?? '')) : '';
                $minCol = $this->columnIndex($idx, ['minimum_quantity', 'الحد الأدنى للكمية', 'الحد الأدنى', 'الحد الادنى']);
                $minQty = ($minCol !== null && array_key_exists($minCol, $line) && $line[$minCol] !== null && $line[$minCol] !== '')
                    ? (float) $line[$minCol]
                    : null;

                $stockId = Stock::query()->where('name', $warehouse)->value('id');

                $existing = Category::query()
                    ->where('warehouse', $warehouse)
                    ->whereRaw('TRIM(category_name) = ?', [$name])
                    ->first();

                if ($existing) {
                    $upd = [
                        'category_price' => $price,
                        'stock_id' => $stockId ?: $existing->stock_id,
                        'production_id' => $productionId,
                        'measurement_id' => $measurementId,
                        'item_code' => $sku !== '' ? $sku : $existing->item_code,
                    ];
                    if ($colorVal !== '') {
                        $upd['color'] = $colorVal;
                    }
                    if ($refVal !== '') {
                        $upd['ref'] = $refVal;
                    }
                    if ($minQty !== null) {
                        $upd['minimum_quantity'] = $minQty;
                    }
                    $existing->update($upd);
                    $updated++;
                } else {
                    $q = Category::query()->create([
                        'category_name' => $name,
                        'category_price' => $price,
                        'unit_price' => $price,
                        'initial_balance' => 0,
                        'minimum_quantity' => $minQty ?? 0,
                        'warehouse' => $warehouse,
                        'production_id' => $productionId,
                        'measurement_id' => $measurementId,
                        'stock_id' => $stockId,
                        'item_code' => $sku !== '' ? $sku : null,
                        'total_price' => 0,
                        'sell_total_price' => 0,
                        'color' => $colorVal !== '' ? $colorVal : null,
                        'ref' => $refVal !== '' ? $refVal : null,
                        'category_image' => '',
                    ]);
                    $q->quantity = 0;
                    $q->save();
                    $created++;
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['created' => $created, 'updated' => $updated], 200);
    }

    /**
     * Import category rows from the recipe-style Excel sheet (اسم الصنف / الخامات / الكمية …)
     * without creating recipes. All rows go to the warehouse chosen in the request.
     */
    public function importRecipeSheetItems(Request $request, RecipeSheetImportService $import): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:xlsx,xls,csv'],
            'warehouse' => ['required', 'string', 'max:255'],
            'sheet' => ['nullable', 'string', 'max:255'],
            'include_products' => ['sometimes', 'boolean'],
            'include_materials' => ['sometimes', 'boolean'],
        ]);

        try {
            $warehouse = $this->resolveDefaultWarehouse($data['warehouse'], '');
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($warehouse === '') {
            return response()->json(['message' => 'يجب تحديد المخزن.'], 422);
        }

        $uploaded = $request->file('file');
        $path = $uploaded->getRealPath();
        if ($path === false || ! is_readable($path)) {
            return response()->json(['message' => 'تعذّر قراءة الملف المرفوع.'], 422);
        }

        try {
            $result = $import->importItemsOnly(
                absolutePath: $path,
                warehouse: $warehouse,
                sheetName: $data['sheet'] ?? null,
                includeProducts: $request->boolean('include_products', true),
                includeMaterials: $request->boolean('include_materials', true),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'تعذّر استيراد الأصناف: '.$e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'تم استيراد الأصناف من شيت Excel',
            ...$result->toArray(),
        ]);
    }

    /**
     * Columns: match (name or sku), quantity, unit_cost, warehouse (optional — matches stocks.name)
     */
    public function importOpeningBalances(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt',
            'create_missing_items' => 'sometimes|boolean',
        ]);

        $createMissing = $request->boolean('create_missing_items', false);
        $path = $request->file('file')->getRealPath();
        $sheet = $this->loadSpreadsheet($path)->getActiveSheet();
        $rows = $sheet->toArray();
        if (count($rows) < 2) {
            return response()->json(['message' => 'Empty sheet'], 422);
        }

        $headers = $this->headerRow($rows[0]);
        $idx = array_flip($headers);

        $ledger = app(InventoryMovementLedgerService::class);
        $gl = app(InventoryGlPostingService::class);

        $processed = 0;
        $defaultProductionId = (int) (Production::query()->value('id') ?: 1);

        DB::beginTransaction();
        try {
            for ($r = 1; $r < count($rows); $r++) {
                $line = $rows[$r];
                $matchCol = $this->columnIndex($idx, ['name', 'sku', 'match', 'اسم الصنف', 'الصنف', 'كود الصنف', 'الباركود']);
                $match = $matchCol !== null ? trim((string) ($line[$matchCol] ?? '')) : '';
                $qtyCol = $this->columnIndex($idx, ['quantity', 'الكمية']);
                $qty = $qtyCol !== null ? (float) ($line[$qtyCol] ?? 0) : 0.0;
                $costCol = $this->columnIndex($idx, ['unit_cost', 'cost', 'تكلفة الوحدة', 'سعر الوحدة']);
                $unitCost = $costCol !== null ? (float) ($line[$costCol] ?? 0) : 0.0;
                $warehouseCol = $this->columnIndex($idx, ['warehouse', 'المخزن', 'مخزن']);
                $warehouse = $warehouseCol !== null ? trim((string) ($line[$warehouseCol] ?? '')) : '';

                if ($match === '' || abs($qty) < 0.0000001) {
                    continue;
                }

                $q = Category::query()
                    ->when($warehouse !== '', fn ($q) => $q->where('warehouse', $warehouse))
                    ->where(function ($q) use ($match) {
                        $q->whereRaw('TRIM(category_name) = ?', [$match])
                            ->orWhere('item_code', $match);
                    })
                    ->first();

                if (! $q && $createMissing) {
                    $wh = $warehouse !== '' ? $warehouse : 'مخزن مواد خام';
                    $stockId = Stock::query()->where('name', $wh)->value('id');
                    $newProdId = $this->resolveProductionIdFromLine($line, $idx, $defaultProductionId);
                    $q = Category::query()->create([
                        'category_name' => $match,
                        'category_price' => $unitCost,
                        'unit_price' => $unitCost,
                        'initial_balance' => 0,
                        'minimum_quantity' => 0,
                        'warehouse' => $wh,
                        'production_id' => $newProdId,
                        'measurement_id' => 1,
                        'stock_id' => $stockId,
                        'total_price' => 0,
                        'sell_total_price' => 0,
                        'category_image' => '',
                    ]);
                    $q->quantity = 0;
                    $q->save();
                }

                if (! $q) {
                    continue;
                }

                $this->applyOptionalCategoryFieldsFromRow($q, $line, $idx);

                $ledger->recordInbound(
                    $q,
                    InventoryMovementType::OpeningBalance,
                    $qty,
                    $unitCost,
                    $qty * $unitCost,
                    true,
                    'excel_opening_balance',
                    (int) $q->id,
                    'افتتاحي — استيراد Excel',
                    null,
                    auth()->user()->name ?? null
                );

                $inv = TreeAccount::resolveInventoryAccountForCategoryId((int) $q->id);
                $gl->postOpeningInventory($qty * $unitCost, 'افتتاحي Excel — ' . $q->category_name, auth()->id(), $inv);

                $processed++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['processed_lines' => $processed], 200);
    }

    /**
     * Columns: match, counted_quantity (physical), warehouse optional — variance posts adjustment GL.
     */
    public function importAdjustments(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt',
        ]);

        $path = $request->file('file')->getRealPath();
        $sheet = $this->loadSpreadsheet($path)->getActiveSheet();
        $rows = $sheet->toArray();
        if (count($rows) < 2) {
            return response()->json(['message' => 'Empty sheet'], 422);
        }

        $headers = $this->headerRow($rows[0]);
        $idx = array_flip($headers);

        $ledger = app(InventoryMovementLedgerService::class);
        $gl = app(InventoryGlPostingService::class);

        $processed = 0;

        DB::beginTransaction();
        try {
            for ($r = 1; $r < count($rows); $r++) {
                $line = $rows[$r];
                $matchCol = $this->columnIndex($idx, ['name', 'sku', 'match', 'اسم الصنف', 'الصنف', 'كود الصنف', 'الباركود']);
                $match = $matchCol !== null ? trim((string) ($line[$matchCol] ?? '')) : '';
                $countedCol = $this->columnIndex($idx, ['counted_quantity', 'physical', 'الكمية الفعلية', 'الكمية المجرودة', 'الجرد الفعلي', 'كمية الجرد']);
                $counted = $countedCol !== null ? (float) ($line[$countedCol] ?? 0) : 0.0;
                $warehouseCol = $this->columnIndex($idx, ['warehouse', 'المخزن', 'مخزن']);
                $warehouse = $warehouseCol !== null ? trim((string) ($line[$warehouseCol] ?? '')) : '';

                if ($match === '') {
                    continue;
                }

                $cat = Category::query()
                    ->when($warehouse !== '', fn ($q) => $q->where('warehouse', $warehouse))
                    ->where(function ($q) use ($match) {
                        $q->whereRaw('TRIM(category_name) = ?', [$match])
                            ->orWhere('item_code', $match);
                    })
                    ->first();

                if (! $cat) {
                    continue;
                }

                $this->applyOptionalCategoryFieldsFromRow($cat, $line, $idx);

                $systemQty = (float) ($cat->fresh()->quantity ?? 0);
                $delta = $counted - $systemQty;
                if (abs($delta) < 0.0000001) {
                    continue;
                }

                $avg = \App\Services\CategoryInventoryCostService::averageCostForCategoryIssue((int) $cat->id);
                $inv = TreeAccount::resolveInventoryAccountForCategoryId((int) $cat->id);

                if ($delta > 0) {
                    $ledger->recordInbound(
                        $cat,
                        InventoryMovementType::AdjustmentGain,
                        abs($delta),
                        $avg,
                        abs($delta) * $avg,
                        true,
                        'excel_inventory_adjustment',
                        (int) $cat->id,
                        'جرد — زيادة (Excel)',
                        null,
                        auth()->user()->name ?? null
                    );
                    if ($inv) {
                        $gl->postPhysicalCountGain(abs($delta) * $avg, $inv, 'جرد زيادة — ' . $cat->category_name, auth()->id());
                    }
                } else {
                    $ledger->recordOutbound(
                        $cat,
                        InventoryMovementType::AdjustmentLoss,
                        abs($delta),
                        $avg,
                        abs($delta) * $avg,
                        true,
                        'excel_inventory_adjustment',
                        (int) $cat->id,
                        'جرد — عجز (Excel)',
                        null,
                        auth()->user()->name ?? null
                    );
                    if ($inv) {
                        $gl->postPhysicalCountLoss(abs($delta) * $avg, $inv, 'جرد عجز — ' . $cat->category_name, auth()->id());
                    }
                }

                $processed++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['processed_lines' => $processed], 200);
    }
}
