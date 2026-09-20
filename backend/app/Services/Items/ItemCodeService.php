<?php

namespace App\Services\Items;

use App\Models\Item;
use App\Models\Stock;
use Illuminate\Database\QueryException;

class ItemCodeService
{
    public function ensureCode(Item $item): void
    {
        if ($item->item_code !== null && $item->item_code !== '') {
            return;
        }

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $item->item_code = $this->generateSequentialCode($item);
            try {
                $item->save();

                return;
            } catch (QueryException $e) {
                if (! $this->isUniqueViolation($e)) {
                    throw $e;
                }
                $item->item_code = null;
            }
        }

        throw new \RuntimeException('Could not generate a unique item_code.');
    }

    public function generateSequentialCode(Item $item): string
    {
        return $this->nextNumericCode($this->prefixForItem($item));
    }

    public function nextCodeForWarehouse(?string $warehouse): string
    {
        return $this->nextNumericCode($this->prefixForWarehouse($warehouse));
    }

    public function prefixForWarehouse(?string $warehouse): string
    {
        $type = $this->warehouseTypeFromName($warehouse);

        return $this->prefixForWarehouseType($type);
    }

    public function prefixForItem(Item $item): string
    {
        $type = '';
        if ($item->relationLoaded('stock')) {
            $type = trim((string) ($item->stock->warehouse_type ?? ''));
        } elseif ($item->stock_id) {
            $type = trim((string) (Stock::query()->whereKey($item->stock_id)->value('warehouse_type') ?? ''));
        }
        if ($type !== '') {
            return $this->prefixForWarehouseType($type);
        }

        return $this->prefixForWarehouse($item->warehouse);
    }

    /**
     * كود عشوائي احتياطي (وصفات / تعارض إصدار).
     */
    public function generateUniqueCode(): string
    {
        $prefix = (string) config('items_import.item_code_prefix', 'ITM-');

        for ($i = 0; $i < 50; $i++) {
            $suffix = strtoupper(bin2hex(random_bytes(4)));
            $code = $prefix.$suffix;
            if (! Item::where('item_code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Could not generate a unique item_code.');
    }

    /**
     * أصناف النطاق (مخزن و/أو معرفات) مرتبة للتوليد المتسلسل.
     *
     * @param  list<int>|null  $ids
     */
    public function scopedItemsQuery(?string $warehouse = null, ?array $ids = null)
    {
        $q = Item::query();

        $warehouse = trim((string) $warehouse);
        if ($warehouse !== '') {
            $q->where('warehouse', $warehouse);
        }

        if ($ids !== null) {
            $ids = array_values(array_filter(array_map('intval', $ids)));
            if ($ids === []) {
                $q->whereRaw('1 = 0');
            } else {
                $q->whereIn('id', $ids);
            }
        }

        return $q;
    }

    /**
     * أصناف بلا كود (فارغ أو مسافات) مع فلتر مخزن أو قائمة معرفات اختيارية.
     *
     * @param  list<int>|null  $ids
     */
    public function missingCodeQuery(?string $warehouse = null, ?array $ids = null)
    {
        return $this->scopedItemsQuery($warehouse, $ids)->where(function ($inner) {
            $inner->whereNull('item_code')->orWhereRaw("TRIM(item_code) = ''");
        });
    }

    /**
     * @param  list<int>|null  $ids
     * @return array{
     *   missing: int,
     *   mismatched: int,
     *   matching: int,
     *   warehouses: list<array{warehouse: string, prefix: string, missing: int, mismatched: int, matching: int, count: int, next_code: string}>
     * }
     */
    public function previewMissing(?string $warehouse = null, ?array $ids = null): array
    {
        $items = $this->scopedItemsQuery($warehouse, $ids)
            ->with(['stock:id,warehouse_type'])
            ->orderBy('warehouse')
            ->orderBy('id')
            ->get(['id', 'warehouse', 'item_code', 'stock_id']);

        $grouped = [];
        $missing = 0;
        $mismatched = 0;
        $matching = 0;

        foreach ($items as $item) {
            $name = trim((string) ($item->warehouse ?? ''));
            $key = $name !== '' ? $name : 'بدون مخزن';
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'warehouse' => $key,
                    'prefix' => $this->prefixForWarehouse($name),
                    'missing' => 0,
                    'mismatched' => 0,
                    'matching' => 0,
                    'count' => 0,
                    'next_code' => $this->nextCodeForWarehouse($name),
                ];
            }

            $status = $this->codeStatus($item);
            $grouped[$key][$status]++;
            if ($status === 'missing') {
                $missing++;
                $grouped[$key]['count']++;
            } elseif ($status === 'mismatched') {
                $mismatched++;
                $grouped[$key]['count']++;
            } else {
                $matching++;
            }
        }

        return [
            'missing' => $missing,
            'mismatched' => $mismatched,
            'matching' => $matching,
            'warehouses' => array_values($grouped),
        ];
    }

    /**
     * يملأ الأكواد الفارغة بالتسلسل حسب المخزن.
     * إذا $replaceExisting = true يُعاد أيضاً أي كود لا يطابق بادئة المخزن (مثل ITM-…).
     *
     * @param  list<int>|null  $ids
     * @return array{assigned: int, replaced: int, failed: int, warehouses: array<string, int>, failures: list<array{id: int, message: string}>}
     */
    public function assignMissing(?string $warehouse = null, ?array $ids = null, bool $replaceExisting = false): array
    {
        $items = $this->scopedItemsQuery($warehouse, $ids)
            ->with(['stock:id,warehouse_type'])
            ->orderBy('warehouse')
            ->orderBy('id')
            ->get();

        $nextByPrefix = [];
        $assigned = 0;
        $replaced = 0;
        $byWarehouse = [];
        $failures = [];

        foreach ($items as $item) {
            $status = $this->codeStatus($item);
            if ($status === 'matching') {
                continue;
            }
            if ($status === 'mismatched' && ! $replaceExisting) {
                continue;
            }

            $prefix = $this->prefixForItem($item);
            if (! isset($nextByPrefix[$prefix])) {
                $nextByPrefix[$prefix] = (int) $this->nextNumericCode($prefix);
            }

            $saved = false;
            $previous = trim((string) ($item->item_code ?? ''));
            for ($attempt = 0; $attempt < 50; $attempt++) {
                $code = (string) ($nextByPrefix[$prefix] + $attempt);
                if (Item::query()->where('item_code', $code)->exists()) {
                    continue;
                }
                $item->item_code = $code;
                try {
                    $item->save();
                    $nextByPrefix[$prefix] = (int) $code + 1;
                    $assigned++;
                    if ($previous !== '') {
                        $replaced++;
                    }
                    $wh = trim((string) $item->warehouse);
                    $wh = $wh !== '' ? $wh : 'بدون مخزن';
                    $byWarehouse[$wh] = ($byWarehouse[$wh] ?? 0) + 1;
                    $saved = true;
                    break;
                } catch (QueryException $e) {
                    if (! $this->isUniqueViolation($e)) {
                        $failures[] = ['id' => (int) $item->id, 'message' => $e->getMessage()];
                        $saved = true;
                        break;
                    }
                    $item->item_code = $previous !== '' ? $previous : null;
                }
            }

            if (! $saved) {
                $failures[] = ['id' => (int) $item->id, 'message' => 'تعذر توليد كود فريد'];
            }
        }

        return [
            'assigned' => $assigned,
            'replaced' => $replaced,
            'failed' => count($failures),
            'warehouses' => $byWarehouse,
            'failures' => $failures,
        ];
    }

    /** @return 'missing'|'mismatched'|'matching' */
    private function codeStatus(Item $item): string
    {
        $code = trim((string) ($item->item_code ?? ''));
        if ($code === '') {
            return 'missing';
        }

        $prefix = $this->prefixForItem($item);
        if (preg_match('/^'.preg_quote($prefix, '/').'\d+$/', $code)) {
            return 'matching';
        }

        return 'mismatched';
    }

    public function assertCodeAvailable(?string $code, ?int $exceptItemId = null): void
    {
        if ($code === null || $code === '') {
            return;
        }

        $q = Item::query()->where('item_code', $code);
        if ($exceptItemId !== null) {
            $q->where('id', '!=', $exceptItemId);
        }
        if ($q->exists()) {
            throw new \InvalidArgumentException('Duplicate item_code: '.$code);
        }
    }

    /**
     * كود يعكس نفس المنتج مع رقم إصدار جديد (مثال: 101001-R2) لتمييز النسخة بعد تعديل الوصفة.
     */
    public function suggestSuccessorCode(Item $old, int $nextRevision): string
    {
        $base = $old->item_code;
        if ($base === null || $base === '') {
            $base = 'ID'.$old->id;
        }
        $suffix = '-R'.$nextRevision;
        $maxLen = 64;
        $suffixLen = mb_strlen($suffix);
        $trimBase = mb_substr($base, 0, max(1, $maxLen - $suffixLen));
        $candidate = $trimBase.$suffix;
        if (! Item::where('item_code', $candidate)->exists()) {
            return $candidate;
        }

        return $this->generateUniqueCode();
    }

    private function nextNumericCode(string $prefix): string
    {
        $serialStart = (int) (config('item_codes.serial_start') ?: 1001);
        $start = (int) ($prefix.(string) $serialStart);
        $pattern = '/^'.preg_quote($prefix, '/').'\d+$/';

        $codes = Item::query()
            ->whereNotNull('item_code')
            ->where('item_code', '!=', '')
            ->where('item_code', 'like', $prefix.'%')
            ->pluck('item_code');

        $max = $start - 1;
        foreach ($codes as $code) {
            $code = trim((string) $code);
            if ($code === '' || ! preg_match($pattern, $code)) {
                continue;
            }
            $n = (int) $code;
            if ($n > $max) {
                $max = $n;
            }
        }

        $candidate = max($start, $max + 1);

        for ($i = 0; $i < 200; $i++) {
            $code = (string) ($candidate + $i);
            if (! Item::query()->where('item_code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Could not generate a unique item_code.');
    }

    private function warehouseTypeFromName(?string $warehouse): string
    {
        $w = trim((string) $warehouse);
        if ($w === '') {
            return 'default';
        }

        $names = $this->warehouseNames();
        if (isset($names[$w]) && is_string($names[$w]) && $names[$w] !== '') {
            return $names[$w];
        }

        $type = Stock::query()->where('name', $w)->value('warehouse_type');
        $type = is_string($type) ? trim($type) : '';
        if ($type !== '') {
            return $type;
        }

        return 'default';
    }

    private function prefixForWarehouseType(string $type): string
    {
        $prefixes = $this->prefixes();
        $type = trim($type);
        if ($type !== '' && isset($prefixes[$type]) && $prefixes[$type] !== '') {
            return (string) $prefixes[$type];
        }

        return (string) ($prefixes['default'] ?? '90');
    }

    /**
     * @return array<string, string>
     */
    private function prefixes(): array
    {
        $fromConfig = config('item_codes.prefixes');
        if (is_array($fromConfig) && $fromConfig !== []) {
            return $fromConfig;
        }

        return [
            'raw_materials' => '10',
            'wip' => '20',
            'finished_goods' => '30',
            'operating_supplies' => '40',
            'maintenance' => '50',
            'damaged' => '60',
            'materials_at_vendor' => '70',
            'default' => '90',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function warehouseNames(): array
    {
        $fromConfig = config('item_codes.warehouse_names');
        if (is_array($fromConfig) && $fromConfig !== []) {
            return $fromConfig;
        }

        return [
            'مخزن مواد خام' => 'raw_materials',
            'مخزن منتج تحت التشغيل' => 'wip',
            'مخزن منتج تام' => 'finished_goods',
            'مستلزمات تشغيل وأدوات تشغيل' => 'operating_supplies',
            'مخزن صيانة' => 'maintenance',
            'مخزن تالف' => 'damaged',
            'مخزن التجهيز' => 'materials_at_vendor',
            'مواد لدى مندوب' => 'materials_at_vendor',
            'مخزن مواد لدى مندوب' => 'materials_at_vendor',
            'مخزون لدى معالج خارجي' => 'materials_at_vendor',
        ];
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000' || $driverCode === 1062 || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
