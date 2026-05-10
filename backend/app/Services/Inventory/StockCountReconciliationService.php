<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Models\Category;
use App\Models\InventoryCountImport;
use App\Models\InventoryCountImportRow;
use App\Models\Production;
use App\Models\Stock;
use App\Models\TreeAccount;
use App\Services\Accounting\InventoryGlPostingService;
use App\Services\CategoryInventoryCostService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class StockCountReconciliationService
{
    private ArabicFuzzyMatcher $matcher;
    private WarehouseClassifier $classifier;
    private InventoryMovementLedgerService $ledger;
    private InventoryGlPostingService $gl;

    public function __construct(
        ArabicFuzzyMatcher $matcher,
        WarehouseClassifier $classifier,
        InventoryMovementLedgerService $ledger,
        InventoryGlPostingService $gl
    ) {
        $this->matcher = $matcher;
        $this->classifier = $classifier;
        $this->ledger = $ledger;
        $this->gl = $gl;
    }

    /**
     * Step 1: Parse the Excel and produce a preview analysis.
     */
    public function preview(string $filePath, string $originalFilename, ?int $userId = null): InventoryCountImport
    {
        $spreadsheet = $this->loadSpreadsheet($filePath);
        $allSheetRows = $this->extractAllSheetRows($spreadsheet);

        $token = Str::random(48);
        $import = InventoryCountImport::create([
            'import_token' => $token,
            'filename' => $originalFilename,
            'status' => 'pending',
            'user_id' => $userId,
            'expires_at' => now()->addHours(2),
        ]);

        $this->matcher->clearCache();

        $parsedRows = [];
        $seenProducts = [];

        foreach ($allSheetRows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $qty = $this->parseNumber($row['quantity'] ?? 0);
            $warehouseName = trim((string) ($row['warehouse'] ?? ''));
            $unitCost = isset($row['unit_cost']) ? $this->parseNumber($row['unit_cost']) : null;

            if ($name === '') {
                continue;
            }

            $normalizedKey = ArabicFuzzyMatcher::normalize($name) . '||' . $warehouseName;

            $isDuplicate = false;
            $mergedInto = null;
            if (isset($seenProducts[$normalizedKey])) {
                $isDuplicate = true;
                $mergedInto = $seenProducts[$normalizedKey];
                $existingRow = $parsedRows[$mergedInto] ?? null;
                if ($existingRow) {
                    $parsedRows[$mergedInto]['excel_quantity'] += $qty;
                }
            } else {
                $seenProducts[$normalizedKey] = count($parsedRows);
            }

            $match = $this->matcher->findBestMatch($name, null, $warehouseName ?: null);

            $systemQty = null;
            $diff = null;
            $direction = null;
            if ($match['category']) {
                $systemQty = (float) ($match['category']->quantity ?? 0);
                $effectiveQty = $isDuplicate ? ($parsedRows[$mergedInto]['excel_quantity'] ?? $qty) : $qty;
                $diff = $effectiveQty - $systemQty;
                $direction = $diff > 0 ? 'in' : ($diff < 0 ? 'out' : null);
            }

            $classification = null;
            if (!$match['category']) {
                $classification = $this->classifier->classify($name, $warehouseName ?: null);
            }

            $rowWarnings = [];
            if ($match['match_type'] === 'fuzzy' && $match['confidence'] < 85) {
                $rowWarnings[] = 'مطابقة ضبابية منخفضة الثقة (' . round($match['confidence'], 1) . '%) — تحقق يدويًا';
            }
            if (!$match['category']) {
                $rowWarnings[] = 'صنف جديد — سيتم إنشاؤه بسعر 0';
            }
            if ($diff !== null && $diff < 0) {
                $rowWarnings[] = 'عجز: ' . abs($diff) . ' وحدة';
            }
            if ($diff !== null && abs($diff) > 100) {
                $rowWarnings[] = 'فرق كبير في الكمية: ' . abs($diff);
            }
            if ($classification && $classification['method'] === 'default_fallback') {
                $rowWarnings[] = 'تصنيف تلقائي غير مؤكد — تم تعيينه كمواد خام افتراضيًا';
            }

            $parsedRows[] = [
                'excel_row_number' => $row['_row_number'],
                'excel_product_name' => $name,
                'excel_warehouse_name' => $warehouseName,
                'excel_quantity' => $isDuplicate ? ($parsedRows[$mergedInto]['excel_quantity'] ?? $qty) : $qty,
                'excel_unit_cost' => $unitCost,
                'match_type' => $match['match_type'],
                'match_confidence' => $match['confidence'],
                'matched_name' => $match['category'] ? $match['category']->category_name : null,
                'matched_category_id' => $match['category'] ? $match['category']->id : null,
                'system_quantity' => $systemQty,
                'quantity_difference' => $diff,
                'adjustment_direction' => $direction,
                'assigned_stock_id' => $match['category']
                    ? $match['category']->stock_id
                    : ($classification['stock_id'] ?? null),
                'assigned_warehouse_name' => $match['category']
                    ? ($match['category']->warehouse ?? '')
                    : ($classification['warehouse_name'] ?? 'مخزن مواد خام'),
                'classification_method' => $classification['method'] ?? null,
                'is_new_product' => $match['category'] === null,
                'is_duplicate_row' => $isDuplicate,
                'merged_into_row_id' => $mergedInto,
                'warnings' => $rowWarnings,
                'row_status' => 'pending',
            ];
        }

        // Persist rows
        $importRows = [];
        foreach ($parsedRows as $pr) {
            $pr['import_id'] = $import->id;
            $importRows[] = InventoryCountImportRow::create($pr);
        }

        // Summary
        $matched = collect($parsedRows)->where('is_new_product', false)->where('is_duplicate_row', false)->count();
        $newItems = collect($parsedRows)->where('is_new_product', true)->where('is_duplicate_row', false)->count();
        $duplicates = collect($parsedRows)->where('is_duplicate_row', true)->count();
        $totalActive = collect($parsedRows)->where('is_duplicate_row', false)->count();

        $allWarnings = collect($parsedRows)->pluck('warnings')->flatten()->filter()->values()->all();

        $import->update([
            'status' => 'previewed',
            'total_rows' => $totalActive,
            'matched_rows' => $matched,
            'new_items_rows' => $newItems,
            'previewed_at' => now(),
            'warnings' => $allWarnings,
            'summary' => [
                'total_rows' => $totalActive,
                'matched' => $matched,
                'new_items' => $newItems,
                'duplicates_merged' => $duplicates,
                'with_adjustment' => collect($parsedRows)->where('is_duplicate_row', false)->whereNotNull('quantity_difference')->filter(fn ($r) => abs($r['quantity_difference']) > 0.000001)->count(),
                'with_gain' => collect($parsedRows)->where('adjustment_direction', 'in')->where('is_duplicate_row', false)->count(),
                'with_loss' => collect($parsedRows)->where('adjustment_direction', 'out')->where('is_duplicate_row', false)->count(),
                'low_confidence_matches' => collect($parsedRows)->where('match_type', 'fuzzy')->where('is_duplicate_row', false)->filter(fn ($r) => $r['match_confidence'] < 85)->count(),
            ],
        ]);

        return $import->fresh()->load('rows');
    }

    /**
     * Step 2: Confirm and apply the import.
     */
    public function confirm(string $importToken, ?int $userId = null): InventoryCountImport
    {
        $import = InventoryCountImport::where('import_token', $importToken)
            ->where('status', 'previewed')
            ->firstOrFail();

        if ($import->isExpired()) {
            $import->update(['status' => 'failed']);
            throw new \RuntimeException('انتهت صلاحية جلسة الاستيراد. يرجى إعادة الرفع.');
        }

        $rows = $import->rows()
            ->where('row_status', 'pending')
            ->where('is_duplicate_row', false)
            ->orderBy('excel_row_number')
            ->get();

        $defaultProductionId = (int) (Production::query()->value('id') ?: 1);
        $adjusted = 0;
        $created = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                try {
                    if ($row->is_new_product) {
                        $cat = $this->createNewProduct($row, $defaultProductionId);
                        $row->update([
                            'matched_category_id' => $cat->id,
                            'system_quantity' => 0,
                            'quantity_difference' => $row->excel_quantity,
                            'adjustment_direction' => $row->excel_quantity > 0 ? 'in' : null,
                        ]);
                        $created++;

                        if (abs($row->excel_quantity) > 0.000001) {
                            $this->applyAdjustment($cat, (float) $row->excel_quantity, 0, $userId);
                            $adjusted++;
                        }
                    } else {
                        $cat = Category::query()->find($row->matched_category_id);
                        if (!$cat) {
                            $row->update(['row_status' => 'error', 'error_message' => 'Category not found']);
                            $errors[] = "Row {$row->excel_row_number}: Category #{$row->matched_category_id} not found";
                            continue;
                        }

                        $systemQty = (float) ($cat->fresh()->quantity ?? 0);
                        $diff = (float) $row->excel_quantity - $systemQty;

                        if (abs($diff) > 0.000001) {
                            $this->applyAdjustment($cat, $diff, $systemQty, $userId);
                            $adjusted++;
                        }
                    }

                    $row->update(['row_status' => 'applied']);
                } catch (\Throwable $e) {
                    $row->update([
                        'row_status' => 'error',
                        'error_message' => mb_substr($e->getMessage(), 0, 1000),
                    ]);
                    $errors[] = "Row {$row->excel_row_number}: {$e->getMessage()}";
                }
            }

            $this->syncStandardStockBalancesFromCategoryQuantities();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $import->update(['status' => 'failed']);
            throw $e;
        }

        $import->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'adjusted_rows' => $adjusted,
            'new_items_rows' => $created,
            'summary' => array_merge($import->summary ?? [], [
                'applied' => $adjusted,
                'created' => $created,
                'errors' => count($errors),
                'error_details' => array_slice($errors, 0, 50),
            ]),
        ]);

        return $import->fresh()->load('rows');
    }

    /**
     * يحدّث حقل balance في جدول stocks ليطابق مجموع كميات الأصناف لكل مخزن قياسي
     * (للواجهات التي تعتمد على /stocks).
     */
    private function syncStandardStockBalancesFromCategoryQuantities(): void
    {
        $names = [
            'مخزن مواد خام',
            'مخزن منتج تحت التشغيل',
            'مخزن منتج تام',
            'مخزن صيانة',
            'مخزن تالف',
        ];
        foreach ($names as $name) {
            $stock = Stock::query()->where('name', $name)->first();
            if (! $stock) {
                continue;
            }
            $qtySum = (float) Category::query()->where('warehouse', $name)->sum('quantity');
            $stock->update(['balance' => $qtySum]);
        }
    }

    /**
     * Cancel a pending import.
     */
    public function cancel(string $importToken): void
    {
        $import = InventoryCountImport::where('import_token', $importToken)
            ->whereIn('status', ['pending', 'previewed'])
            ->firstOrFail();

        $import->update(['status' => 'cancelled']);
    }

    private function createNewProduct(InventoryCountImportRow $row, int $defaultProductionId): Category
    {
        $stockId = $row->assigned_stock_id;
        $warehouse = $row->assigned_warehouse_name ?: 'مخزن مواد خام';

        if (!$stockId) {
            $stockId = Stock::query()->where('name', $warehouse)->value('id');
        }

        return Category::create([
            'category_name' => $row->excel_product_name,
            'category_price' => 0,
            'unit_price' => 0,
            'total_price' => 0,
            'sell_total_price' => 0,
            'initial_balance' => 0,
            'minimum_quantity' => 0,
            'quantity' => 0,
            'warehouse' => $warehouse,
            'production_id' => $defaultProductionId,
            'measurement_id' => 1,
            'stock_id' => $stockId,
            'category_image' => '',
            'ref' => 'auto-stock-count',
        ]);
    }

    private function applyAdjustment(Category $cat, float $diff, float $systemQty, ?int $userId): void
    {
        $avg = CategoryInventoryCostService::averageCostForCategoryIssue((int) $cat->id);
        $inv = TreeAccount::resolveInventoryAccountForCategoryId((int) $cat->id);
        $performer = null;
        try {
            $performer = auth()->user()->name ?? null;
        } catch (\Throwable $e) {
        }

        if ($diff > 0) {
            $this->ledger->recordInbound(
                $cat,
                InventoryMovementType::AdjustmentGain,
                abs($diff),
                $avg > 0 ? $avg : 0,
                abs($diff) * ($avg > 0 ? $avg : 0),
                true,
                'stock_count_reconciliation',
                (int) $cat->id,
                'جرد فعلي — زيادة (تسوية Excel)',
                null,
                $performer
            );
            if ($inv && $avg > 0) {
                $this->gl->postPhysicalCountGain(
                    abs($diff) * $avg,
                    $inv,
                    'جرد زيادة — ' . $cat->category_name,
                    $userId
                );
            }
        } else {
            $currentQty = (float) ($cat->fresh()->quantity ?? 0);

            if ($currentQty < abs($diff)) {
                // Force set quantity to the Excel value (which is lower)
                $absDiff = $currentQty; // drain what's available
                if ($absDiff > 0.000001) {
                    $this->ledger->recordOutbound(
                        $cat,
                        InventoryMovementType::AdjustmentLoss,
                        $absDiff,
                        $avg,
                        $absDiff * $avg,
                        true,
                        'stock_count_reconciliation',
                        (int) $cat->id,
                        'جرد فعلي — عجز (تسوية Excel)',
                        null,
                        $performer
                    );
                    if ($inv && $avg > 0) {
                        $this->gl->postPhysicalCountLoss($absDiff * $avg, $inv, 'جرد عجز — ' . $cat->category_name, $userId);
                    }
                }

                // Force remaining quantity to match Excel via direct update
                $targetQty = max(0, $systemQty + $diff);
                $remainingQty = (float) ($cat->fresh()->quantity ?? 0);
                if (abs($remainingQty - $targetQty) > 0.000001) {
                    $cat->refresh();
                    $cat->quantity = $targetQty;
                    $cat->save();
                    $this->ledger->refreshMirrorBalances((int) $cat->id);
                }
            } else {
                $this->ledger->recordOutbound(
                    $cat,
                    InventoryMovementType::AdjustmentLoss,
                    abs($diff),
                    $avg,
                    abs($diff) * $avg,
                    true,
                    'stock_count_reconciliation',
                    (int) $cat->id,
                    'جرد فعلي — عجز (تسوية Excel)',
                    null,
                    $performer
                );
                if ($inv && $avg > 0) {
                    $this->gl->postPhysicalCountLoss(abs($diff) * $avg, $inv, 'جرد عجز — ' . $cat->category_name, $userId);
                }
            }
        }
    }

    // ─── Spreadsheet Helpers ───────────────────────────────────

    private function loadSpreadsheet(string $path): Spreadsheet
    {
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'], true)) {
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

        $tmp = tempnam(sys_get_temp_dir(), 'scr_csv_');
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
     * Extract rows from ALL sheets in the workbook.
     * Supports two layouts:
     *   A) Standard: one header row, one name column, one qty column
     *   B) Multi-group: warehouse name in row above, repeated column groups
     *      (البيانات/الكمية/السعر/ملاحظات) side-by-side across columns
     */
    private function extractAllSheetRows(Spreadsheet $spreadsheet): array
    {
        $allRows = [];
        $rowNumber = 1;

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $rows = $sheet->toArray(null, true, true, false);
            if (count($rows) < 2) {
                continue;
            }

            $sheetTitle = $sheet->getTitle();

            // Try multi-group layout first (detects repeated البيانات/الكمية columns)
            $multiGroupRows = $this->tryExtractMultiGroupLayout($rows, $sheetTitle, $rowNumber);
            if (!empty($multiGroupRows)) {
                $allRows = array_merge($allRows, $multiGroupRows);
                $rowNumber += count($multiGroupRows);
                continue;
            }

            // Fall back to standard single-header layout
            $standardRows = $this->tryExtractStandardLayout($rows, $sheetTitle, $rowNumber);
            if (!empty($standardRows)) {
                $allRows = array_merge($allRows, $standardRows);
                $rowNumber += count($standardRows);
            }
        }

        return $allRows;
    }

    private function tryExtractMultiGroupLayout(array $rows, string $sheetTitle, int &$rowNumber): array
    {
        $nameAliases = ['البيانات', 'الخامات', 'الاصناف', 'اسم الصنف', 'الصنف', 'اسم'];
        $qtyAliases = ['الكمية', 'الكميه', 'كمية', 'quantity', 'qty'];
        $costAliases = ['السعر', 'سعر', 'التكلفة', 'price', 'cost'];
        $notesAliases = ['ملاحظات', 'notes', 'remarks'];
        $unitAliases = ['الوحدة', 'الوحده', 'وحدة', 'unit'];

        // Scan first 3 rows to find the header row with repeated column groups
        $headerRowIdx = null;
        $warehouseRowIdx = null;
        $headerCells = [];

        for ($scanIdx = 0; $scanIdx < min(5, count($rows)); $scanIdx++) {
            $row = $rows[$scanIdx];
            $nameHits = 0;
            foreach ($row as $cell) {
                if ($cell === null) continue;
                $cellLower = mb_strtolower(trim((string) $cell));
                foreach ($nameAliases as $alias) {
                    if ($cellLower === $alias) {
                        $nameHits++;
                        break;
                    }
                }
            }
            // If we find 2+ "name"-type headers, this is the multi-group header row
            if ($nameHits >= 2) {
                $headerRowIdx = $scanIdx;
                $warehouseRowIdx = $scanIdx > 0 ? $scanIdx - 1 : null;
                $headerCells = $row;
                break;
            }
        }

        if ($headerRowIdx === null) {
            return [];
        }

        // Parse column groups: find each "name" column and its adjacent qty/price columns
        $groups = [];
        $normalizedHeaders = [];
        foreach ($headerCells as $colIdx => $cell) {
            $normalizedHeaders[$colIdx] = ($cell !== null) ? mb_strtolower(trim((string) $cell)) : '';
        }

        // Find warehouse name row (row above headers)
        $warehouseRow = $warehouseRowIdx !== null ? ($rows[$warehouseRowIdx] ?? []) : [];

        foreach ($normalizedHeaders as $colIdx => $headerVal) {
            $isNameCol = false;
            foreach ($nameAliases as $alias) {
                if ($headerVal === $alias) {
                    $isNameCol = true;
                    break;
                }
            }
            if (!$isNameCol) continue;

            // Find qty and price columns near this name column (within next 5 columns)
            $qtyCol = null;
            $costCol = null;
            $unitCol = null;

            for ($offset = -2; $offset <= 6; $offset++) {
                $checkIdx = $colIdx + $offset;
                if ($checkIdx === $colIdx || $checkIdx < 0 || !isset($normalizedHeaders[$checkIdx])) continue;

                $val = $normalizedHeaders[$checkIdx];

                if ($qtyCol === null) {
                    foreach ($qtyAliases as $alias) {
                        if ($val === $alias) { $qtyCol = $checkIdx; break; }
                    }
                }
                if ($costCol === null) {
                    foreach ($costAliases as $alias) {
                        if ($val === $alias) { $costCol = $checkIdx; break; }
                    }
                }
                if ($unitCol === null) {
                    foreach ($unitAliases as $alias) {
                        if ($val === $alias) { $unitCol = $checkIdx; break; }
                    }
                }
            }

            // Determine warehouse name from the row above this group
            $warehouseName = '';
            if (!empty($warehouseRow)) {
                // Search backwards from name column to find the nearest non-empty cell in warehouse row
                for ($w = $colIdx; $w >= max(0, $colIdx - 6); $w--) {
                    if (isset($warehouseRow[$w]) && $warehouseRow[$w] !== null && trim((string) $warehouseRow[$w]) !== '') {
                        $warehouseName = trim((string) $warehouseRow[$w]);
                        break;
                    }
                }
                // Also check forward
                if ($warehouseName === '') {
                    for ($w = $colIdx; $w <= min(count($warehouseRow) - 1, $colIdx + 6); $w++) {
                        if (isset($warehouseRow[$w]) && $warehouseRow[$w] !== null && trim((string) $warehouseRow[$w]) !== '') {
                            $warehouseName = trim((string) $warehouseRow[$w]);
                            break;
                        }
                    }
                }
            }

            // Map warehouse name to standard system names
            $mappedWarehouse = $this->mapWarehouseName($warehouseName);

            $groups[] = [
                'nameCol' => $colIdx,
                'qtyCol' => $qtyCol,
                'costCol' => $costCol,
                'warehouseName' => $warehouseName,
                'mappedWarehouse' => $mappedWarehouse,
            ];
        }

        if (empty($groups)) {
            return [];
        }

        // Extract data rows for each group
        $result = [];
        for ($r = $headerRowIdx + 1; $r < count($rows); $r++) {
            $line = $rows[$r];

            foreach ($groups as $group) {
                $name = isset($line[$group['nameCol']]) ? trim((string) ($line[$group['nameCol']] ?? '')) : '';
                if ($name === '') continue;

                // Skip if the "name" looks like a row number only
                if (is_numeric($name) && (float) $name == (int) $name && strlen($name) <= 3) {
                    continue;
                }

                $qty = 0;
                if ($group['qtyCol'] !== null && isset($line[$group['qtyCol']])) {
                    $qty = $this->parseNumber($line[$group['qtyCol']]);
                }

                $unitCost = null;
                if ($group['costCol'] !== null && isset($line[$group['costCol']]) && $line[$group['costCol']] !== null && $line[$group['costCol']] !== '') {
                    $unitCost = $this->parseNumber($line[$group['costCol']]);
                }

                $result[] = [
                    '_row_number' => $rowNumber++,
                    '_sheet' => $group['warehouseName'] ?: 'Sheet',
                    'name' => $name,
                    'quantity' => $qty,
                    'warehouse' => $group['mappedWarehouse'],
                    'unit_cost' => $unitCost,
                ];
            }
        }

        return $result;
    }

    private function tryExtractStandardLayout(array $rows, string $sheetTitle, int &$rowNumber): array
    {
        $nameAliases = ['name', 'category_name', 'product', 'item', 'product name', 'اسم الصنف', 'الصنف', 'اسم', 'الاصناف', 'البيانات', 'الخامات'];
        $qtyAliases = ['quantity', 'qty', 'الكمية', 'كمية', 'الكميه'];
        $warehouseAliases = ['warehouse', 'المخزن', 'مخزن', 'store'];
        $costAliases = ['unit_cost', 'cost', 'price', 'سعر', 'التكلفة', 'تكلفة الوحدة', 'سعر الوحدة', 'السعر'];

        // Find header row
        $headerIdx = null;
        $headers = [];
        foreach ($rows as $idx => $row) {
            $nonEmpty = array_filter($row, fn ($c) => $c !== null && trim((string) $c) !== '');
            if (count($nonEmpty) >= 2) {
                $headerIdx = $idx;
                $headers = array_map(function ($c) {
                    if ($c === null || (is_numeric($c) && !is_string($c))) {
                        return '__empty_' . mt_rand(100000, 999999);
                    }
                    return mb_strtolower(trim((string) $c));
                }, $row);
                break;
            }
        }

        if ($headerIdx === null) {
            return [];
        }

        // Build column index, handling duplicate header names
        $idx = [];
        foreach ($headers as $colIdx => $headerVal) {
            if (!isset($idx[$headerVal])) {
                $idx[$headerVal] = $colIdx;
            }
        }

        $nameCol = $this->findCol($idx, $nameAliases);
        $qtyCol = $this->findCol($idx, $qtyAliases);

        if ($nameCol === null) {
            return [];
        }

        $warehouseCol = $this->findCol($idx, $warehouseAliases);
        $costCol = $this->findCol($idx, $costAliases);
        $sheetWarehouse = $this->detectWarehouseFromTitle($sheetTitle);

        $result = [];
        for ($r = $headerIdx + 1; $r < count($rows); $r++) {
            $line = $rows[$r];
            $name = isset($line[$nameCol]) ? trim((string) ($line[$nameCol] ?? '')) : '';
            if ($name === '') {
                continue;
            }

            $qty = 0;
            if ($qtyCol !== null && isset($line[$qtyCol])) {
                $qty = $this->parseNumber($line[$qtyCol]);
            }

            $warehouse = '';
            if ($warehouseCol !== null && isset($line[$warehouseCol])) {
                $warehouse = trim((string) $line[$warehouseCol]);
            }
            if ($warehouse === '' && $sheetWarehouse) {
                $warehouse = $sheetWarehouse;
            }

            $unitCost = null;
            if ($costCol !== null && isset($line[$costCol]) && $line[$costCol] !== '' && $line[$costCol] !== null) {
                $unitCost = $this->parseNumber($line[$costCol]);
            }

            $result[] = [
                '_row_number' => $rowNumber++,
                '_sheet' => $sheetTitle,
                'name' => $name,
                'quantity' => $qty,
                'warehouse' => $warehouse,
                'unit_cost' => $unitCost,
            ];
        }

        return $result;
    }

    private function findCol(array $idx, array $candidates): ?int
    {
        foreach ($candidates as $c) {
            $k = mb_strtolower(trim($c));
            if ($k !== '' && isset($idx[$k])) {
                return $idx[$k];
            }
        }
        return null;
    }

    /**
     * Map free-text warehouse name from Excel to a standard system warehouse.
     */
    private function mapWarehouseName(string $name): string
    {
        if ($name === '') {
            return 'مخزن مواد خام';
        }

        $n = ArabicFuzzyMatcher::normalize($name);

        // Check for exact match in stocks table first
        $stock = Stock::query()->where('name', $name)->first();
        if ($stock) {
            return $stock->name;
        }

        // Keyword mapping
        $patterns = [
            'مخزن منتج تام' => ['تام', 'نهائي', 'جاهز', 'finished', 'منتج تام'],
            'مخزن منتج تحت التشغيل' => ['تشغيل', 'تحت', 'wip', 'شبه', 'نصف'],
            'مخزن صيانة' => ['صيانه', 'صيانة', 'maintenance'],
            'مخزن تالف' => ['تالف', 'defective', 'damaged'],
            'مخزن مواد خام' => ['خام', 'خامات', 'raw', 'مواد', 'material'],
        ];

        foreach ($patterns as $stdName => $keywords) {
            foreach ($keywords as $kw) {
                if (mb_stripos($n, $kw) !== false) {
                    return $stdName;
                }
            }
        }

        // Default: classify based on product naming (best effort)
        return 'مخزن مواد خام';
    }

    private function detectWarehouseFromTitle(string $title): ?string
    {
        $n = ArabicFuzzyMatcher::normalize($title);
        $map = [
            'خام' => 'مخزن مواد خام',
            'raw' => 'مخزن مواد خام',
            'تشغيل' => 'مخزن منتج تحت التشغيل',
            'wip' => 'مخزن منتج تحت التشغيل',
            'تام' => 'مخزن منتج تام',
            'finished' => 'مخزن منتج تام',
            'صيانه' => 'مخزن صيانة',
            'صيانة' => 'مخزن صيانة',
            'تالف' => 'مخزن تالف',
        ];
        foreach ($map as $keyword => $warehouse) {
            if (mb_stripos($n, $keyword) !== false) {
                return $warehouse;
            }
        }
        return null;
    }

    private function parseNumber($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        $str = trim((string) $value);
        $str = str_replace(',', '', $str);
        return is_numeric($str) ? (float) $str : 0.0;
    }
}
