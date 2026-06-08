<?php

namespace App\Services\Orders;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\ShippingMethod;
use App\Models\customerCompany;

class OrderEditApplyService
{
    /** @var array<string, string> */
    public const ORDER_FIELD_LABELS = [
        'customer_name' => 'اسم العميل',
        'customer_phone_1' => 'الهاتف 1',
        'customer_phone_2' => 'الهاتف 2',
        'tel' => 'التلفون الأرضي',
        'governorate' => 'المحافظة',
        'city' => 'المدينة',
        'address' => 'العنوان',
        'customer_type' => 'نوع العميل',
        'company_id' => 'الشركة',
        'shipping_method_id' => 'طريقة الشحن',
        'shipping_cost' => 'مصاريف الشحن',
        'shipping_revenue' => 'إيراد الشحن',
        'courier_shipping_cost' => 'تكلفة الشحن للمندوب',
        'prepaid_amount' => 'المبلغ المدفوع',
        'discount' => 'الخصم',
        'total_invoice' => 'إجمالي الفاتورة',
        'net_total' => 'الصافي',
        'vat' => 'القيمة المضافة',
        'bank_id' => 'البنك',
        'collect_note' => 'ملحوظة التحصيل',
        'order_image' => 'صورة الإيصال',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    public function collectOrderFieldChanges(Order $order, array $payload): array
    {
        $changes = [];

        foreach (array_keys(self::ORDER_FIELD_LABELS) as $field) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }

            $new = $payload[$field];

            if ($field === 'shipping_method_id') {
                $old = (int) $order->shipping_method_id;
                $new = (int) $new;
                if ($old === $new) {
                    continue;
                }
                $oldLabel = $order->shipping_method?->name ?? (string) $old;
                $newLabel = ShippingMethod::query()->where('id', $new)->value('name') ?? (string) $new;
                $changes[] = sprintf('%s: «%s» ← «%s»', self::ORDER_FIELD_LABELS[$field], $newLabel, $oldLabel);
                continue;
            }

            if ($field === 'company_id') {
                $old = (int) ($order->company_id ?? 0);
                $new = (int) $new;
                if ($old === $new) {
                    continue;
                }
                $oldLabel = $old > 0
                    ? (customerCompany::query()->where('id', $old)->value('name') ?? (string) $old)
                    : '—';
                $newLabel = $new > 0
                    ? (customerCompany::query()->where('id', $new)->value('name') ?? (string) $new)
                    : '—';
                $changes[] = sprintf('%s: «%s» ← «%s»', self::ORDER_FIELD_LABELS[$field], $newLabel, $oldLabel);
                continue;
            }

            if ($field === 'bank_id') {
                $old = $order->bank_id;
                $new = $new === 'null' || $new === '' ? null : $new;
                if ((string) ($old ?? '') === (string) ($new ?? '')) {
                    continue;
                }
                $changes[] = sprintf(
                    '%s: «%s» ← «%s»',
                    self::ORDER_FIELD_LABELS[$field],
                    $new !== null && $new !== '' ? (string) $new : '—',
                    $old !== null && $old !== '' ? (string) $old : '—'
                );
                continue;
            }

            if ($field === 'order_image') {
                if (! is_string($new) || trim($new) === '' || $new === $order->order_image) {
                    continue;
                }
                $changes[] = sprintf('%s: «%s» ← «%s»', self::ORDER_FIELD_LABELS[$field], $new, $order->order_image ?: '—');
                continue;
            }

            if (in_array($field, ['shipping_cost', 'shipping_revenue', 'courier_shipping_cost', 'prepaid_amount', 'discount', 'total_invoice', 'net_total', 'vat'], true)) {
                if ($this->floatEquals($order->{$field}, $new)) {
                    continue;
                }
                $changes[] = sprintf(
                    '%s: %s ← %s',
                    self::ORDER_FIELD_LABELS[$field],
                    $this->formatNumber($new),
                    $this->formatNumber($order->{$field})
                );
                continue;
            }

            $oldStr = $this->stringValue($order->{$field});
            $newStr = $this->stringValue($new);
            if ($oldStr === $newStr) {
                continue;
            }

            $changes[] = sprintf(
                '%s: «%s» ← «%s»',
                self::ORDER_FIELD_LABELS[$field],
                $newStr !== '' ? $newStr : '—',
                $oldStr !== '' ? $oldStr : '—'
            );
        }

        return $changes;
    }

    /**
     * @param  iterable<int, OrderProduct>  $oldProducts
     * @param  array<int, array<string, mixed>>  $newProducts
     * @return array<int, string>
     */
    public function collectProductChanges(iterable $oldProducts, array $newProducts): array
    {
        $before = [];
        foreach ($oldProducts as $op) {
            $before[] = [
                'category_id' => (int) $op->category_id,
                'name' => (string) ($op->category?->category_name ?? $op->special_details ?? ''),
                'quantity' => (float) $op->quantity,
                'price' => (float) $op->price,
                'special_details' => $op->special_details,
            ];
        }

        $after = [];
        foreach ($newProducts as $row) {
            if (! is_array($row)) {
                continue;
            }
            $categoryId = (int) ($row['category_id'] ?? 0);
            if ($categoryId < 1) {
                continue;
            }
            $qty = (float) ($row['quantity'] ?? 0);
            if ($qty < 1) {
                continue;
            }
            $price = (float) ($row['price'] ?? 0);
            $name = Category::query()->where('id', $categoryId)->value('category_name')
                ?? (string) ($row['special_details'] ?? 'صنف #'.$categoryId);
            $after[] = [
                'category_id' => $categoryId,
                'name' => (string) $name,
                'quantity' => $qty,
                'price' => $price,
                'special_details' => isset($row['special_details']) ? (string) $row['special_details'] : null,
            ];
        }

        $productChanges = $this->diffProducts($before, $after);

        return $productChanges === []
            ? []
            : array_merge(['— تعديلات المنتجات —'], $productChanges);
    }

    /**
     * @param  array<int, string>  $changes
     */
    public function buildTrackingAction(string $userName, array $changes): string
    {
        $action = 'تعديل الطلب — بواسطة '.$userName;
        $note = $this->buildAutoChangeNote($changes);
        if ($note !== null) {
            $action .= ': '.$note;
        }

        return $action;
    }

    /**
     * @param  array<int, string>  $changes
     */
    public function buildAutoChangeNote(array $changes): ?string
    {
        if ($changes === []) {
            return null;
        }

        return implode("\n- ", array_merge([''], $changes));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function filterHeaderPayload(array $payload): array
    {
        $data = [];
        foreach (array_keys(self::ORDER_FIELD_LABELS) as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $payload[$field];
            }
        }

        return $this->normalizeHeaderPayload($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalizeHeaderPayload(array $data): array
    {
        foreach (['customer_name', 'customer_phone_1', 'customer_phone_2', 'governorate', 'address'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $data[$field] = trim((string) ($data[$field] ?? ''));
        }

        foreach (['city', 'tel'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            $data[$field] = ($value === null || trim((string) $value) === '') ? null : trim((string) $value);
        }

        if (array_key_exists('collect_note', $data)) {
            $value = $data['collect_note'];
            $data['collect_note'] = ($value === null || trim((string) $value) === '')
                ? null
                : trim((string) $value);
        }

        if (array_key_exists('bank_id', $data)) {
            $value = $data['bank_id'];
            if ($value === 'null' || $value === '' || $value === null) {
                $data['bank_id'] = null;
            }
        }

        return $data;
    }

    /**
     * @param  array<int, array<string, mixed>>  $before
     * @param  array<int, array<string, mixed>>  $after
     * @return array<int, string>
     */
    private function diffProducts(array $before, array $after): array
    {
        $changes = [];
        $beforeByCat = collect($before)->keyBy('category_id');
        $afterByCat = collect($after)->keyBy('category_id');

        foreach ($afterByCat as $categoryId => $row) {
            if (! $beforeByCat->has($categoryId)) {
                $changes[] = sprintf('إضافة: %s × %s', $row['name'], $this->formatNumber($row['quantity']));
                continue;
            }
            $old = $beforeByCat->get($categoryId);
            if (! $this->floatEquals($old['quantity'], $row['quantity'])) {
                $changes[] = sprintf(
                    'كمية %s: %s ← %s',
                    $row['name'],
                    $this->formatNumber($row['quantity']),
                    $this->formatNumber($old['quantity'])
                );
            }
            if (! $this->floatEquals($old['price'], $row['price'])) {
                $changes[] = sprintf(
                    'سعر %s: %s ← %s',
                    $row['name'],
                    $this->formatNumber($row['price']),
                    $this->formatNumber($old['price'])
                );
            }
            $oldSpecial = $this->stringValue($old['special_details'] ?? null);
            $newSpecial = $this->stringValue($row['special_details'] ?? null);
            if ($oldSpecial !== $newSpecial) {
                $changes[] = sprintf(
                    'تفاصيل خاصة %s: «%s» ← «%s»',
                    $row['name'],
                    $newSpecial !== '' ? $newSpecial : '—',
                    $oldSpecial !== '' ? $oldSpecial : '—'
                );
            }
        }

        foreach ($beforeByCat as $categoryId => $row) {
            if (! $afterByCat->has($categoryId)) {
                $changes[] = sprintf('حذف: %s', $row['name']);
            }
        }

        return $changes;
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private function floatEquals(mixed $a, mixed $b): bool
    {
        return abs((float) $a - (float) $b) < 0.009;
    }

    private function formatNumber(mixed $value): string
    {
        return number_format((float) $value, 2, '.', ',');
    }
}
