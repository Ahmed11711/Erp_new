<?php

namespace App\Services\Purchases;

use App\Enums\PurchaseInvoiceKind;
use App\Models\Purchase;
use App\Models\ShippingCompany;
use App\Models\Supplier;
use App\Services\CategoryInventoryCostService;
use Illuminate\Support\Collection;

class PurchaseEditAuditService
{
    public function __construct(
        private PurchaseInvoiceTypeResolver $typeResolver,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $newProducts
     * @param  array<int, string>  $stockWarnings
     */
    public function buildEditDetails(
        Purchase $oldInvoice,
        Purchase $newRevision,
        Collection $oldLines,
        array $newProducts,
        PurchaseInvoiceKind $oldKind,
        PurchaseInvoiceKind $newKind,
        array $stockWarnings = [],
        ?string $actorName = null,
        ?string $editedAt = null,
    ): string {
        $lines = [];
        $lines[] = 'التاريخ: '.($editedAt ?? now()->format('Y-m-d H:i'));
        $lines[] = 'المستخدم: '.($actorName ?? '—');
        $lines[] = 'مراجعة: #'.$newRevision->id;
        $lines[] = str_repeat('—', 40);

        $this->appendFieldChange($lines, 'حالة الشراء', (string) $oldInvoice->invoice_type, (string) $newRevision->invoice_type);
        $this->appendFieldChange(
            $lines,
            'المورد',
            $this->supplierName((int) $oldInvoice->supplier_id),
            $this->supplierName((int) $newRevision->supplier_id),
        );
        $this->appendFieldChange(
            $lines,
            'مندوب/شركة الشحن',
            $this->shippingName($oldInvoice->shipping_company_id),
            $this->shippingName($newRevision->shipping_company_id),
        );
        $this->appendFieldChange($lines, 'تاريخ الاستلام', (string) $oldInvoice->receipt_date, (string) $newRevision->receipt_date);
        $this->appendFieldChange($lines, 'رقم فاتورة المورد', (string) ($oldInvoice->external_invoice_no ?? ''), (string) ($newRevision->external_invoice_no ?? ''));
        $this->appendFieldChange($lines, 'رقم المستند الداخلي', (string) ($oldInvoice->invoice_no ?? $oldInvoice->invoice_number ?? ''), (string) ($newRevision->invoice_no ?? $newRevision->invoice_number ?? ''));
        $this->appendAmountChange($lines, 'إجمالي البضاعة', (float) ($oldInvoice->product_total ?? 0), (float) ($newRevision->product_total ?? 0));
        $this->appendAmountChange($lines, 'مصاريف الشحن', (float) ($oldInvoice->transport_cost ?? 0), (float) ($newRevision->transport_cost ?? 0));
        $this->appendAmountChange($lines, 'الإجمالي الكلي', (float) ($oldInvoice->grand_total ?? $oldInvoice->total_price ?? 0), (float) ($newRevision->grand_total ?? $newRevision->total_price ?? 0));
        $this->appendAmountChange($lines, 'المبلغ المدفوع', (float) ($oldInvoice->paid_amount ?? 0), (float) ($newRevision->paid_amount ?? 0));
        $this->appendAmountChange($lines, 'المبلغ المتبقي', (float) ($oldInvoice->due_amount ?? 0), (float) ($newRevision->due_amount ?? 0));

        $oldNotes = trim((string) ($oldInvoice->notes ?? ''));
        $newNotes = trim((string) ($newRevision->notes ?? ''));
        if ($oldNotes !== $newNotes) {
            $lines[] = 'ملاحظات الفاتورة: «'.$this->short($oldNotes ?: '—').'» → «'.$this->short($newNotes ?: '—').'»';
        }

        $productChanges = $this->describeProductChanges($oldLines, $oldKind, $newProducts, $newKind);
        if ($productChanges !== []) {
            $lines[] = str_repeat('—', 40);
            $lines[] = 'بنود الفاتورة:';
            foreach ($productChanges as $change) {
                $lines[] = '• '.$change;
            }
        }

        if ($stockWarnings !== []) {
            $lines[] = str_repeat('—', 40);
            $lines[] = 'المخزون:';
            foreach ($stockWarnings as $warning) {
                $lines[] = '• '.$warning;
            }
        }

        if (count($lines) <= 4) {
            $lines[] = 'لا توجد تغييرات جوهرية على البيانات.';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, array<string, mixed>>  $newProducts
     * @return array<int, string>
     */
    private function describeProductChanges(
        Collection $oldLines,
        PurchaseInvoiceKind $oldKind,
        array $newProducts,
        PurchaseInvoiceKind $newKind,
    ): array {
        $oldMap = $this->indexLines($oldLines, $oldKind, true);
        $newMap = $this->indexLines($newProducts, $newKind, false);
        $keys = array_unique(array_merge(array_keys($oldMap), array_keys($newMap)));
        sort($keys);

        $changes = [];
        foreach ($keys as $key) {
            $old = $oldMap[$key] ?? null;
            $new = $newMap[$key] ?? null;

            if ($old === null && $new !== null) {
                $changes[] = 'إضافة: '.$new['label'].' — كمية '.$this->fmtQty($new['qty']).' '.$new['unit']
                    .' × '.$this->fmtAmount($new['price']).' = '.$this->fmtAmount($new['total']);
                continue;
            }

            if ($old !== null && $new === null) {
                $changes[] = 'حذف: '.$old['label'].' — كان '.$this->fmtQty($old['qty']).' '.$old['unit']
                    .' × '.$this->fmtAmount($old['price']).' = '.$this->fmtAmount($old['total']);
                continue;
            }

            if ($old === null || $new === null) {
                continue;
            }

            $parts = [];
            if (abs($old['qty'] - $new['qty']) > 0.000001) {
                $parts[] = 'كمية '.$this->fmtQty($old['qty']).' → '.$this->fmtQty($new['qty']).' '.$new['unit'];
            }
            if (abs($old['price'] - $new['price']) > 0.000001) {
                $parts[] = 'سعر '.$this->fmtAmount($old['price']).' → '.$this->fmtAmount($new['price']);
            }
            if (abs($old['total'] - $new['total']) > 0.000001) {
                $parts[] = 'إجمالي '.$this->fmtAmount($old['total']).' → '.$this->fmtAmount($new['total']);
            }

            if ($parts !== []) {
                $changes[] = 'تعديل: '.$new['label'].' — '.implode(' | ', $parts);
            }
        }

        return $changes;
    }

    /**
     * @param  Collection<int, object>|array<int, array<string, mixed>>  $lines
     * @return array<string, array{label: string, qty: float, unit: string, price: float, total: float}>
     */
    private function indexLines(Collection|array $lines, PurchaseInvoiceKind $kind, bool $fromPersisted): array
    {
        $map = [];
        $isInbound = $this->typeResolver->isInbound($kind);

        foreach ($lines as $line) {
            if ($fromPersisted) {
                $name = (string) $line->product_name;
                $catId = CategoryInventoryCostService::resolveCategoryIdForPurchaseLine($line, $name) ?? 0;
                $qty = abs((float) ($line->product_quantity ?? 0));
                $unit = (string) ($line->product_unit ?? '');
                $price = (float) ($line->product_price ?? 0);
                $total = abs((float) ($line->total ?? 0));
            } else {
                $name = (string) $line['product_name'];
                $catId = CategoryInventoryCostService::resolveCategoryIdForPurchaseLine($line, $name) ?? 0;
                $qty = $this->typeResolver->normalizeQuantity($kind, (float) $line['product_quantity']);
                $unit = (string) ($line['product_unit'] ?? '');
                $price = (float) ($line['product_price'] ?? 0);
                $total = abs((float) ($line['total'] ?? 0));
            }

            if ($qty <= 0.000001 && $total <= 0.000001) {
                continue;
            }

            $key = $catId > 0 ? 'c'.$catId : 'n'.md5($name);
            if (! isset($map[$key])) {
                $map[$key] = [
                    'label' => $name.($catId > 0 ? ' (#'.$catId.')' : ''),
                    'qty' => 0.0,
                    'unit' => $unit,
                    'price' => $price,
                    'total' => 0.0,
                ];
            }

            $map[$key]['qty'] += $qty;
            $map[$key]['total'] += $total;
            $map[$key]['unit'] = $unit ?: $map[$key]['unit'];
            if ($price > 0) {
                $map[$key]['price'] = $price;
            }
        }

        foreach ($map as &$row) {
            if ($row['qty'] > 0.000001 && $row['total'] > 0.000001) {
                $row['price'] = $row['total'] / $row['qty'];
            }
        }
        unset($row);

        return $map;
    }

    /** @param  array<int, string>  $lines */
    private function appendFieldChange(array &$lines, string $label, string $before, string $after): void
    {
        $before = trim($before);
        $after = trim($after);
        if ($before !== $after) {
            $lines[] = $label.': «'.$this->short($before ?: '—').'» → «'.$this->short($after ?: '—').'»';
        }
    }

    /** @param  array<int, string>  $lines */
    private function appendAmountChange(array &$lines, string $label, float $before, float $after): void
    {
        if (abs($before - $after) > 0.009) {
            $lines[] = $label.': '.$this->fmtAmount($before).' → '.$this->fmtAmount($after);
        }
    }

    private function supplierName(?int $supplierId): string
    {
        if (! $supplierId) {
            return '—';
        }

        return (string) (Supplier::query()->where('id', $supplierId)->value('supplier_name') ?? '#'.$supplierId);
    }

    private function shippingName(mixed $shippingCompanyId): string
    {
        $id = (int) $shippingCompanyId;
        if ($id <= 0) {
            return '—';
        }

        return (string) (ShippingCompany::query()->where('id', $id)->value('name') ?? '#'.$id);
    }

    private function fmtAmount(float $value): string
    {
        return number_format($value, 2, '.', ',');
    }

    private function fmtQty(float $value): string
    {
        $formatted = number_format($value, 6, '.', ',');
        $trimmed = rtrim(rtrim($formatted, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }

    private function short(string $value, int $max = 120): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max - 1).'…';
    }
}
