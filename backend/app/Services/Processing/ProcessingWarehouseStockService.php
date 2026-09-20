<?php

namespace App\Services\Processing;

use App\Enums\ProcessingDocumentStatus;
use App\Enums\ProcessingOrderStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * «مخزن التجهيز» — مخزن محسوب من مستندات التشغيل الخارجي.
 *
 * المواد تخرج من المخزن المصدر عند ترحيل إذن الصرف وتدخل هنا منطقياً حتى يتم
 * استلامها، فلا توجد أصناف ظل: الرصيد يُشتق من سطور الأوامر (مصروف − مستلم)
 * والقيمة من تكلفة المادة المسجّلة على السطر، وهي نفس القيمة المرحّلة على
 * حساب المخزون في شجرة الحسابات.
 */
class ProcessingWarehouseStockService
{
    /** شرائح أعمار المواد لدى المعالج بالأيام. */
    private const AGING_BUCKETS = ['0-30', '31-60', '61-90', '90+'];

    /**
     * أرصدة المخزن على مستوى سطر أمر التشغيل.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function balances(array $filters = []): array
    {
        $today = Carbon::today();

        $rows = $this->baseQuery($filters)->get();

        return $rows->map(function ($row) use ($today) {
            $qty = $this->round($row->qty_at_vendor);
            $unitCost = (float) ($row->unit_material_cost ?? 0);
            $firstDispatch = $row->first_dispatch_date ? Carbon::parse($row->first_dispatch_date) : null;
            $ageDays = $firstDispatch ? (int) max(0, $firstDispatch->diffInDays($today, false)) : 0;

            return [
                'processing_order_id' => (int) $row->processing_order_id,
                'processing_order_line_id' => (int) $row->line_id,
                'order_number' => $row->order_number,
                'order_status' => $row->order_status,
                'supplier_id' => (int) $row->supplier_id,
                'supplier_name' => $row->supplier_name,
                'category_id' => (int) $row->category_id,
                'category_name' => $row->category_name,
                'item_code' => $row->item_code,
                'unit' => $row->unit,
                'source_stock_id' => (int) $row->source_stock_id,
                'source_stock_name' => $row->source_stock_name,
                'dispatched_qty' => $this->round($row->dispatched_qty),
                'received_good_qty' => $this->round($row->received_good_qty),
                'received_damaged_qty' => $this->round($row->received_damaged_qty),
                'received_rejected_qty' => $this->round($row->received_rejected_qty),
                'qty_at_vendor' => $qty,
                'unit_cost' => round($unitCost, 4),
                'value_at_vendor' => round($qty * $unitCost, 2),
                'first_dispatch_date' => $firstDispatch?->toDateString(),
                'last_dispatch_date' => $row->last_dispatch_date
                    ? Carbon::parse($row->last_dispatch_date)->toDateString()
                    : null,
                'expected_return_date' => $row->expected_return_date
                    ? Carbon::parse($row->expected_return_date)->toDateString()
                    : null,
                'is_overdue' => $row->expected_return_date
                    ? Carbon::parse($row->expected_return_date)->lt($today)
                    : false,
                'age_days' => $ageDays,
                'aging_bucket' => $this->bucketFor($ageDays),
            ];
        })->values()->all();
    }

    /**
     * إحصائيات المخزن + تجميع حسب المعالج وحسب الصنف + شرائح الأعمار.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function overview(array $filters = []): array
    {
        $rows = $this->balances($filters);

        $totalQty = 0.0;
        $totalValue = 0.0;
        $vendors = [];
        $items = [];
        $aging = array_fill_keys(self::AGING_BUCKETS, ['quantity' => 0.0, 'value' => 0.0, 'lines' => 0]);
        $orderIds = [];
        $oldestDays = 0;
        $overdueLines = 0;

        foreach ($rows as $row) {
            $qty = $row['qty_at_vendor'];
            $value = $row['value_at_vendor'];
            $totalQty += $qty;
            $totalValue += $value;
            $orderIds[$row['processing_order_id']] = true;
            $oldestDays = max($oldestDays, $row['age_days']);
            if ($row['is_overdue']) {
                $overdueLines++;
            }

            $bucket = $row['aging_bucket'];
            $aging[$bucket]['quantity'] += $qty;
            $aging[$bucket]['value'] += $value;
            $aging[$bucket]['lines']++;

            $vendorKey = $row['supplier_id'];
            if (! isset($vendors[$vendorKey])) {
                $vendors[$vendorKey] = [
                    'supplier_id' => $row['supplier_id'],
                    'supplier_name' => $row['supplier_name'],
                    'quantity' => 0.0,
                    'value' => 0.0,
                    'items_count' => 0,
                    'orders' => [],
                    'oldest_age_days' => 0,
                    'overdue_lines' => 0,
                    'lines' => [],
                ];
            }
            $vendors[$vendorKey]['quantity'] += $qty;
            $vendors[$vendorKey]['value'] += $value;
            $vendors[$vendorKey]['orders'][$row['processing_order_id']] = true;
            $vendors[$vendorKey]['oldest_age_days'] = max($vendors[$vendorKey]['oldest_age_days'], $row['age_days']);
            $vendors[$vendorKey]['overdue_lines'] += $row['is_overdue'] ? 1 : 0;
            $vendors[$vendorKey]['lines'][] = $row;

            $itemKey = $row['category_id'];
            if (! isset($items[$itemKey])) {
                $items[$itemKey] = [
                    'category_id' => $row['category_id'],
                    'category_name' => $row['category_name'],
                    'item_code' => $row['item_code'],
                    'unit' => $row['unit'],
                    'quantity' => 0.0,
                    'value' => 0.0,
                    'vendors' => [],
                ];
            }
            $items[$itemKey]['quantity'] += $qty;
            $items[$itemKey]['value'] += $value;
            $items[$itemKey]['vendors'][$row['supplier_id']] = $row['supplier_name'];
        }

        $vendors = array_map(function (array $vendor) {
            $vendor['orders_count'] = count($vendor['orders']);
            $vendor['items_count'] = count(array_unique(array_column($vendor['lines'], 'category_id')));
            $vendor['quantity'] = round($vendor['quantity'], 4);
            $vendor['value'] = round($vendor['value'], 2);
            unset($vendor['orders']);

            return $vendor;
        }, array_values($vendors));

        usort($vendors, fn ($a, $b) => $b['value'] <=> $a['value']);

        $items = array_map(function (array $item) {
            $item['vendors'] = array_values($item['vendors']);
            $item['quantity'] = round($item['quantity'], 4);
            $item['value'] = round($item['value'], 2);

            return $item;
        }, array_values($items));

        usort($items, fn ($a, $b) => $b['value'] <=> $a['value']);

        return [
            'warehouse' => $this->warehouseInfo(),
            'kpis' => [
                'total_quantity' => round($totalQty, 4),
                'total_value' => round($totalValue, 2),
                'vendors_count' => count($vendors),
                'items_count' => count($items),
                'orders_count' => count($orderIds),
                'lines_count' => count($rows),
                'oldest_age_days' => $oldestDays,
                'overdue_lines' => $overdueLines,
            ],
            'aging' => array_map(fn (string $bucket) => [
                'bucket' => $bucket,
                'quantity' => round($aging[$bucket]['quantity'], 4),
                'value' => round($aging[$bucket]['value'], 2),
                'lines' => $aging[$bucket]['lines'],
            ], self::AGING_BUCKETS),
            'vendors' => $vendors,
            'items' => $items,
            'rows' => $rows,
        ];
    }

    /**
     * سجل حركة المخزن: كل إذن صرف (دخول للمخزن) وكل استلام (خروج منه).
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function movements(array $filters = []): array
    {
        $posted = ProcessingDocumentStatus::Posted->value;

        $dispatches = DB::table('processing_dispatch_lines as pdl')
            ->join('processing_dispatch_notes as pdn', 'pdn.id', '=', 'pdl.processing_dispatch_note_id')
            ->join('processing_orders as po', 'po.id', '=', 'pdn.processing_order_id')
            ->join('suppliers as s', 's.id', '=', 'pdn.supplier_id')
            ->leftJoin('categories as c', 'c.id', '=', 'pdl.category_id')
            ->whereNull('pdn.deleted_at')
            ->where('pdn.status', $posted)
            ->selectRaw(implode(', ', [
                "'in' as direction",
                "'dispatch' as document_type",
                'pdn.dispatch_date as movement_date',
                'pdn.dispatch_number as document_number',
                'pdn.id as document_id',
                'po.id as processing_order_id',
                'po.order_number',
                's.id as supplier_id',
                's.supplier_name',
                'pdl.category_id',
                'c.category_name',
                'pdl.quantity as quantity',
                'pdl.unit_cost',
                'pdl.total_cost',
                'pdn.created_at as created_at',
            ]));

        $receipts = DB::table('processing_receipt_lines as prl')
            ->join('processing_receipts as pr', 'pr.id', '=', 'prl.processing_receipt_id')
            ->join('processing_orders as po', 'po.id', '=', 'pr.processing_order_id')
            ->join('suppliers as s', 's.id', '=', 'pr.supplier_id')
            ->leftJoin('categories as c', 'c.id', '=', 'prl.category_id')
            ->whereNull('pr.deleted_at')
            ->where('pr.status', $posted)
            ->selectRaw(implode(', ', [
                "'out' as direction",
                "'receipt' as document_type",
                'pr.receipt_date as movement_date',
                'pr.receipt_number as document_number',
                'pr.id as document_id',
                'po.id as processing_order_id',
                'po.order_number',
                's.id as supplier_id',
                's.supplier_name',
                'prl.category_id',
                'c.category_name',
                '(prl.good_qty + prl.damaged_qty + prl.rejected_qty) as quantity',
                'prl.material_unit_cost as unit_cost',
                '((prl.good_qty + prl.damaged_qty + prl.rejected_qty) * prl.material_unit_cost) as total_cost',
                'pr.created_at as created_at',
            ]));

        $this->applyMovementFilters($dispatches, $filters, 'pdn.dispatch_date');
        $this->applyMovementFilters($receipts, $filters, 'pr.receipt_date');

        $rows = $dispatches->unionAll($receipts)->get()
            ->sortByDesc(fn ($row) => [$row->movement_date, $row->created_at])
            ->values();

        $limit = (int) ($filters['limit'] ?? 500);

        return $rows->take($limit > 0 ? $limit : 500)->map(fn ($row) => [
            'direction' => $row->direction,
            'document_type' => $row->document_type,
            'document_id' => (int) $row->document_id,
            'document_number' => $row->document_number,
            'movement_date' => $row->movement_date ? Carbon::parse($row->movement_date)->toDateString() : null,
            'processing_order_id' => (int) $row->processing_order_id,
            'order_number' => $row->order_number,
            'supplier_id' => (int) $row->supplier_id,
            'supplier_name' => $row->supplier_name,
            'category_id' => (int) $row->category_id,
            'category_name' => $row->category_name,
            'quantity' => $this->round($row->quantity),
            'unit_cost' => round((float) $row->unit_cost, 4),
            'total_cost' => round((float) $row->total_cost, 2),
        ])->all();
    }

    /**
     * قوائم الفلاتر المتاحة (معالجون / أصناف / أوامر لها رصيد قائم).
     *
     * @return array<string, mixed>
     */
    public function filterOptions(): array
    {
        $rows = $this->balances();

        $vendors = [];
        $items = [];
        $orders = [];
        foreach ($rows as $row) {
            $vendors[$row['supplier_id']] = ['id' => $row['supplier_id'], 'name' => $row['supplier_name']];
            $items[$row['category_id']] = ['id' => $row['category_id'], 'name' => $row['category_name']];
            $orders[$row['processing_order_id']] = [
                'id' => $row['processing_order_id'],
                'name' => $row['order_number'],
            ];
        }

        return [
            'vendors' => array_values($vendors),
            'items' => array_values($items),
            'orders' => array_values($orders),
            'aging_buckets' => self::AGING_BUCKETS,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function baseQuery(array $filters): \Illuminate\Database\Query\Builder
    {
        $posted = ProcessingDocumentStatus::Posted->value;

        $dispatchDates = DB::table('processing_dispatch_lines as pdl')
            ->join('processing_dispatch_notes as pdn', 'pdn.id', '=', 'pdl.processing_dispatch_note_id')
            ->whereNull('pdn.deleted_at')
            ->where('pdn.status', $posted)
            ->groupBy('pdl.processing_order_line_id')
            ->select([
                'pdl.processing_order_line_id',
                DB::raw('MIN(pdn.dispatch_date) as first_dispatch_date'),
                DB::raw('MAX(pdn.dispatch_date) as last_dispatch_date'),
            ]);

        $query = DB::table('processing_order_lines as pol')
            ->join('processing_orders as po', 'po.id', '=', 'pol.processing_order_id')
            ->join('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->leftJoin('categories as c', 'c.id', '=', 'pol.category_id')
            ->leftJoin('measurements as m', 'm.id', '=', 'c.measurement_id')
            ->leftJoin('stocks as src', 'src.id', '=', 'po.source_stock_id')
            ->leftJoinSub($dispatchDates, 'dd', 'dd.processing_order_line_id', '=', 'pol.id')
            ->whereNull('po.deleted_at')
            ->where('po.status', '!=', ProcessingOrderStatus::Cancelled->value)
            ->whereRaw('(pol.dispatched_qty - pol.received_good_qty - pol.received_damaged_qty - pol.received_rejected_qty) > 0.000001')
            ->select([
                'pol.id as line_id',
                'pol.processing_order_id',
                'pol.category_id',
                'pol.dispatched_qty',
                'pol.received_good_qty',
                'pol.received_damaged_qty',
                'pol.received_rejected_qty',
                'pol.unit_material_cost',
                DB::raw('(pol.dispatched_qty - pol.received_good_qty - pol.received_damaged_qty - pol.received_rejected_qty) as qty_at_vendor'),
                'po.order_number',
                'po.status as order_status',
                'po.expected_return_date',
                'po.source_stock_id',
                'src.name as source_stock_name',
                's.id as supplier_id',
                's.supplier_name',
                'c.category_name',
                'c.item_code',
                'm.unit',
                'dd.first_dispatch_date',
                'dd.last_dispatch_date',
            ])
            ->orderBy('s.supplier_name')
            ->orderBy('c.category_name');

        if (! empty($filters['supplier_id'])) {
            $query->where('po.supplier_id', (int) $filters['supplier_id']);
        }
        if (! empty($filters['category_id'])) {
            $query->where('pol.category_id', (int) $filters['category_id']);
        }
        if (! empty($filters['processing_order_id'])) {
            $query->where('pol.processing_order_id', (int) $filters['processing_order_id']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('dd.first_dispatch_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('dd.first_dispatch_date', '<=', $filters['date_to']);
        }
        if (! empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('c.category_name', 'like', $term)
                    ->orWhere('c.item_code', 'like', $term)
                    ->orWhere('s.supplier_name', 'like', $term)
                    ->orWhere('po.order_number', 'like', $term);
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyMovementFilters(\Illuminate\Database\Query\Builder $query, array $filters, string $dateColumn): void
    {
        if (! empty($filters['supplier_id'])) {
            $query->where('po.supplier_id', (int) $filters['supplier_id']);
        }
        if (! empty($filters['category_id'])) {
            $query->where('c.id', (int) $filters['category_id']);
        }
        if (! empty($filters['processing_order_id'])) {
            $query->where('po.id', (int) $filters['processing_order_id']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate($dateColumn, '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate($dateColumn, '<=', $filters['date_to']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function warehouseInfo(): array
    {
        $stock = ProcessingWarehouseResolver::materialsAtVendorStock();

        return [
            'id' => $stock?->id,
            'name' => $stock?->name ?? ProcessingWarehouseResolver::WAREHOUSE_NAME,
            'name_en' => $stock?->name_en ?? ProcessingWarehouseResolver::WAREHOUSE_NAME_EN,
            'warehouse_type' => 'materials_at_vendor',
        ];
    }

    private function bucketFor(int $days): string
    {
        return match (true) {
            $days <= 30 => '0-30',
            $days <= 60 => '31-60',
            $days <= 90 => '61-90',
            default => '90+',
        };
    }

    private function round(mixed $value): float
    {
        return round((float) $value, 4);
    }
}
