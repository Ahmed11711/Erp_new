<?php

namespace App\Services\Processing;

use App\Enums\ProcessingOrderStatus;
use App\Models\ProcessingOrder;
use App\Models\ProcessingOrderLine;
use App\Models\Stock;
use App\Models\TransactionType;
use App\Services\Documents\DocumentNumberService;
use App\Services\Manufacturing\ProductionWarehouseResolver;
use Illuminate\Support\Facades\DB;

class ProcessingOrderService
{
    public function __construct(
        private DocumentNumberService $numbers,
        private ProcessingCategoryResolverService $categoryResolver,
        private ProcessingActivityLogger $logger,
    ) {
    }

    public function create(array $data): ProcessingOrder
    {
        return DB::transaction(function () use ($data) {
            $type = TransactionType::query()->where('code', 'PROCESSING_ORDER')->firstOrFail();

            $sourceStockId = (int) ($data['source_stock_id']
                ?? ProductionWarehouseResolver::rawMaterialsStock()?->id);
            $destStockId = (int) ($data['destination_stock_id']
                ?? $sourceStockId);

            if (! $sourceStockId) {
                throw new \InvalidArgumentException('تعذر تحديد مخزن المواد الخام.');
            }

            $order = ProcessingOrder::query()->create([
                'order_number' => $this->numbers->generate((int) $type->id),
                'supplier_id' => (int) $data['supplier_id'],
                'source_stock_id' => $sourceStockId,
                'destination_stock_id' => $destStockId ?: null,
                'status' => ($data['auto_approve'] ?? false)
                    ? ProcessingOrderStatus::Approved->value
                    : ProcessingOrderStatus::Draft->value,
                'expected_return_date' => $data['expected_return_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'expected_service_total' => (float) ($data['expected_service_total'] ?? 0),
                'created_by' => auth()->id(),
                'approved_by' => ($data['auto_approve'] ?? false) ? auth()->id() : null,
                'approved_at' => ($data['auto_approve'] ?? false) ? now() : null,
            ]);

            $this->syncLines($order, $data['lines'] ?? []);

            $this->logger->logOrder($order, 'created');

            return $order->fresh(['lines.category', 'supplier', 'sourceStock', 'destinationStock']);
        });
    }

    public function update(ProcessingOrder $order, array $data): ProcessingOrder
    {
        if ($order->status !== ProcessingOrderStatus::Draft->value) {
            throw new \InvalidArgumentException('لا يمكن تعديل أمر ليس في حالة مسودة.');
        }

        return DB::transaction(function () use ($order, $data) {
            $order->update([
                'supplier_id' => (int) ($data['supplier_id'] ?? $order->supplier_id),
                'source_stock_id' => (int) ($data['source_stock_id'] ?? $order->source_stock_id),
                'destination_stock_id' => (int) ($data['destination_stock_id'] ?? $order->destination_stock_id),
                'expected_return_date' => $data['expected_return_date'] ?? $order->expected_return_date,
                'notes' => $data['notes'] ?? $order->notes,
                'expected_service_total' => (float) ($data['expected_service_total'] ?? $order->expected_service_total),
            ]);

            if (isset($data['lines'])) {
                $order->lines()->delete();
                $this->syncLines($order, $data['lines']);
            }

            $this->logger->logOrder($order, 'updated');

            return $order->fresh(['lines.category', 'supplier', 'sourceStock', 'destinationStock']);
        });
    }

    public function approve(ProcessingOrder $order): ProcessingOrder
    {
        if ($order->status !== ProcessingOrderStatus::Draft->value) {
            throw new \InvalidArgumentException('الأمر ليس في حالة مسودة.');
        }

        $order->update([
            'status' => ProcessingOrderStatus::Approved->value,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        $this->logger->logOrder($order, 'approved');

        return $order->fresh();
    }

    public function enrichForDisplay(ProcessingOrder $order): ProcessingOrder
    {
        $this->repairLineCategories($order);
        $order->loadMissing('sourceStock', 'lines.category');

        foreach ($order->lines as $line) {
            $resolved = $this->resolveSourceCategory($order, $line->category);
            $available = (float) ($resolved->quantity ?? 0);
            $orderRemaining = max(0, (float) $line->ordered_qty - (float) $line->dispatched_qty);

            $line->setAttribute('available_qty', $available);
            $line->setAttribute('dispatchable_qty', min($orderRemaining, $available));
            $line->setAttribute('resolved_category_id', (int) $resolved->id);
        }

        return $order;
    }

    public function repairLineCategories(ProcessingOrder $order): void
    {
        $order->loadMissing('lines.category', 'sourceStock');
        $atVendorStock = ProcessingWarehouseResolver::ensureMaterialsAtVendorStock();

        $destStock = $order->destinationStock ?? $order->sourceStock;

        foreach ($order->lines as $line) {
            $picked = $line->category;
            if (! $picked) {
                continue;
            }

            $source = $this->resolveSourceCategory($order, $picked);
            $atVendor = $this->categoryResolver->resolveInStock($source, $atVendorStock);
            $destCat = $destStock
                ? $this->categoryResolver->resolveInStock($source, $destStock)
                : null;

            $dirty = false;
            if ((int) $line->category_id !== (int) $source->id) {
                $line->category_id = $source->id;
                $dirty = true;
            }
            if ((int) $line->at_vendor_category_id !== (int) $atVendor->id) {
                $line->at_vendor_category_id = $atVendor->id;
                $dirty = true;
            }
            if ($destCat && (int) $line->destination_category_id !== (int) $destCat->id) {
                $line->destination_category_id = $destCat->id;
                $dirty = true;
            }

            if ($dirty) {
                $line->save();
                $line->setRelation('category', $source);
            }
        }
    }

    public function resolveSourceCategory(ProcessingOrder $order, \App\Models\Category $category): \App\Models\Category
    {
        $stock = $order->sourceStock;
        if (! $stock && $order->source_stock_id) {
            $stock = Stock::query()->find($order->source_stock_id);
        }
        if (! $stock) {
            $stock = ProductionWarehouseResolver::rawMaterialsStock();
        }
        if (! $stock) {
            return $category;
        }

        return $this->categoryResolver->resolveInStock($category, $stock);
    }

    public function assertSufficientStock(ProcessingOrder $order, \App\Models\Category $category, float $qty): void
    {
        $resolved = $this->resolveSourceCategory($order, $category);
        $available = (float) ($resolved->quantity ?? 0);

        if ($qty > $available + 0.000001) {
            throw new \InvalidArgumentException(sprintf(
                'رصيد غير كافٍ للصنف «%s» في %s. المتاح: %s — المطلوب: %s',
                $resolved->category_name,
                $resolved->warehouse ?: ($order->sourceStock->name ?? 'مخزن المواد الخام'),
                rtrim(rtrim(number_format($available, 4, '.', ''), '0'), '.') ?: '0',
                rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.') ?: '0',
            ));
        }
    }

    public function refreshOrderTotals(ProcessingOrder $order): void
    {
        $order->load('lines');
        $dispatched = $order->lines->sum('dispatched_qty');
        $received = $order->lines->sum(fn ($l) => (float) $l->received_good_qty);

        $status = $order->status;
        if ($dispatched > 0 && $status === ProcessingOrderStatus::Approved->value) {
            $status = ProcessingOrderStatus::InProgress->value;
        }

        $allDone = $order->lines->every(function (ProcessingOrderLine $line) {
            $atVendor = $line->qtyAtVendor();

            return $atVendor <= 0.000001
                && (float) $line->dispatched_qty >= (float) $line->ordered_qty - 0.000001;
        });

        if ($allDone && $dispatched > 0) {
            $status = ProcessingOrderStatus::Completed->value;
        } elseif ($received > 0 && ! $allDone) {
            $status = ProcessingOrderStatus::PartiallyReceived->value;
        }

        $order->update([
            'total_dispatched_qty' => $dispatched,
            'total_received_qty' => $received,
            'status' => $status,
        ]);
    }

    private function syncLines(ProcessingOrder $order, array $lines): void
    {
        $atVendorStock = ProcessingWarehouseResolver::ensureMaterialsAtVendorStock();

        $destStock = $order->destinationStock ?? $order->sourceStock;

        $sourceStock = $order->sourceStock ?? Stock::query()->find($order->source_stock_id);
        if (! $sourceStock) {
            $sourceStock = ProductionWarehouseResolver::rawMaterialsStock();
        }

        foreach ($lines as $row) {
            $picked = \App\Models\Category::query()->findOrFail((int) $row['category_id']);
            $sourceCategory = $sourceStock
                ? $this->categoryResolver->resolveInStock($picked, $sourceStock)
                : $picked;

            $orderedQty = (float) $row['ordered_qty'];

            $atVendorCat = $this->categoryResolver->resolveInStock($sourceCategory, $atVendorStock);
            $destCat = $destStock
                ? $this->categoryResolver->resolveInStock($sourceCategory, $destStock)
                : null;

            ProcessingOrderLine::query()->create([
                'processing_order_id' => $order->id,
                'category_id' => $sourceCategory->id,
                'at_vendor_category_id' => $atVendorCat->id,
                'destination_category_id' => $destCat?->id,
                'ordered_qty' => $orderedQty,
                'expected_service_amount' => (float) ($row['expected_service_amount'] ?? 0),
                'notes' => $row['notes'] ?? null,
            ]);
        }
    }
}
