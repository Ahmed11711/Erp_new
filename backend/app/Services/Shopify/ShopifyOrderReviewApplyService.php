<?php

namespace App\Services\Shopify;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderProductArchive;
use App\Models\ShippingMethod;
use Illuminate\Support\Facades\DB;

class ShopifyOrderReviewApplyService
{
    /** @var array<string, string> */
    private const ORDER_FIELD_LABELS = [
        'customer_name' => 'اسم العميل',
        'customer_phone_1' => 'الهاتف 1',
        'customer_phone_2' => 'الهاتف 2',
        'tel' => 'التلفون الأرضي',
        'governorate' => 'المحافظة',
        'city' => 'المدينة',
        'address' => 'العنوان',
        'shipping_method_id' => 'طريقة الشحن',
        'shipping_cost' => 'مصاريف الشحن',
        'prepaid_amount' => 'المبلغ المدفوع',
        'discount' => 'الخصم',
        'total_invoice' => 'إجمالي الفاتورة',
        'net_total' => 'الصافي',
        'vat' => 'القيمة المضافة',
        'collect_note' => 'ملحوظة التحصيل',
    ];

    /**
     * @param  array<string, mixed>  $orderPayload
     * @param  array<int, array<string, mixed>>|null  $productsPayload
     * @return array{changes: array<int, string>, order: Order}
     */
    public function apply(Order $order, array $orderPayload, ?array $productsPayload, ?string $manualNote, int $userId): array
    {
        if ($order->shopify_order_id === null) {
            throw new \InvalidArgumentException('الطلب ليس مستورداً من Shopify.');
        }

        $changes = [];

        DB::transaction(function () use ($order, $orderPayload, $productsPayload, $manualNote, $userId, &$changes) {
            $order->load(['order_products.category', 'shipping_method']);

            $updateData = $this->normalizeOrderUpdateData($orderPayload);

            $changes = array_merge(
                $changes,
                $this->collectOrderFieldChanges($order, $updateData)
            );

            if ($productsPayload !== null) {
                $changes = array_merge(
                    $changes,
                    $this->applyProducts($order, $productsPayload)
                );
            }

            if ($updateData !== []) {
                $order->update($updateData);
            }

            $autoNote = $this->buildAutoChangeNote($changes);
            $manualTrimmed = is_string($manualNote) && trim($manualNote) !== '' ? trim($manualNote) : null;
            $reviewNoteParts = array_filter([$autoNote, $manualTrimmed]);
            if ($autoNote !== null && $manualTrimmed !== null && str_contains($manualTrimmed, $autoNote)) {
                $reviewNoteParts = [$manualTrimmed];
            }
            $reviewNote = $reviewNoteParts !== [] ? implode("\n\n", $reviewNoteParts) : null;

            // أعد حساب علامة «يحتاج مراجعة منتج»: تبقى true طالما هناك بند ما زال مربوطاً بصنف placeholder.
            $placeholderId = app(ShopifyOrderImportService::class)->unmatchedPlaceholderCategoryId();
            $stillNeedsProductReview = OrderProduct::query()
                ->where('order_id', $order->id)
                ->where('category_id', $placeholderId)
                ->exists();

            $order->update([
                'shopify_reviewed_at' => now(),
                'shopify_reviewed_by_user_id' => $userId,
                'shopify_review_note' => $reviewNote,
                'shopify_needs_product_review' => $stillNeedsProductReview,
            ]);

            if ($autoNote !== null) {
                DB::table('notes')->insert([
                    'order_id' => $order->id,
                    'user_id' => $userId,
                    'note' => $autoNote,
                    'added_from' => 'مراجعة Shopify',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $trackingAction = 'مراجعة طلب Shopify';
            if ($changes !== []) {
                $trackingAction .= ' ('.count($changes).' تعديل)';
            }

            $createdAt = now();
            DB::table('trackings')->insert([
                'order_id' => $order->id,
                'date' => $createdAt->toDateString(),
                'action' => $trackingAction,
                'user_id' => $userId,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        });

        return [
            'changes' => $changes,
            'order' => $order->fresh([
                'shopifyReviewer:id,name',
                'shipping_method',
                'order_products.category',
                'order_source',
                'note.user',
            ]) ?? $order,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function collectOrderFieldChanges(Order $order, array $payload): array
    {
        $changes = [];
        $allowed = array_keys(self::ORDER_FIELD_LABELS);

        foreach ($allowed as $field) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }

            $old = $order->{$field};
            $new = $payload[$field];

            if ($field === 'shipping_method_id') {
                $old = $order->shipping_method_id;
                $new = (int) $new;
                if ((int) $old === $new) {
                    continue;
                }
                $oldLabel = $order->shipping_method?->name ?? (string) $old;
                $newLabel = ShippingMethod::query()->where('id', $new)->value('name') ?? (string) $new;
                $changes[] = sprintf(
                    '%s: «%s» ← «%s»',
                    self::ORDER_FIELD_LABELS[$field],
                    $oldLabel,
                    $newLabel
                );
                continue;
            }

            if (in_array($field, ['shipping_cost', 'prepaid_amount', 'discount', 'total_invoice', 'net_total', 'vat'], true)) {
                if ($this->floatEquals($old, $new)) {
                    continue;
                }
                $changes[] = sprintf(
                    '%s: %s ← %s',
                    self::ORDER_FIELD_LABELS[$field],
                    $this->formatNumber($new),
                    $this->formatNumber($old)
                );
                continue;
            }

            $oldStr = $this->stringValue($old);
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
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeOrderUpdateData(array $payload): array
    {
        $data = $this->filterOrderUpdateData($payload);

        /** أعمدة NOT NULL في orders — لا تُمرَّر null */
        $notNullStrings = [
            'customer_name',
            'customer_phone_1',
            'customer_phone_2',
            'governorate',
            'address',
        ];

        foreach ($notNullStrings as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            if ($value === null) {
                $data[$field] = '';
            } else {
                $data[$field] = trim((string) $value);
            }
        }

        foreach (['city', 'tel'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            if ($value === null || trim((string) $value) === '') {
                $data[$field] = null;
            } else {
                $data[$field] = trim((string) $value);
            }
        }

        if (array_key_exists('collect_note', $data)) {
            $value = $data['collect_note'];
            $data['collect_note'] = ($value === null || trim((string) $value) === '')
                ? null
                : trim((string) $value);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function filterOrderUpdateData(array $payload): array
    {
        $data = [];
        foreach (array_keys(self::ORDER_FIELD_LABELS) as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $payload[$field];
            }
        }

        return $data;
    }

    /**
     * @param  array<int, array<string, mixed>>  $productsPayload
     * @return array<int, string>
     */
    private function applyProducts(Order $order, array $productsPayload): array
    {
        $changes = [];
        $before = $order->order_products->map(fn (OrderProduct $op) => [
            'category_id' => (int) $op->category_id,
            'name' => (string) ($op->category?->category_name ?? $op->special_details ?? ''),
            'quantity' => (float) $op->quantity,
            'price' => (float) $op->price,
        ])->values()->all();

        $after = [];
        foreach ($productsPayload as $row) {
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
                'total' => round($qty * $price, 2),
            ];
        }

        $productChanges = $this->diffProducts($before, $after);
        if ($productChanges === [] && $this->productsStructurallyEqual($before, $after)) {
            return [];
        }

        $archiveRows = [];
        foreach ($order->order_products as $op) {
            $archiveRows[] = [
                'order_id' => $order->id,
                'category_id' => $op->category_id,
                'quantity' => $op->quantity,
                'shipped_quantity' => $op->shipped_quantity,
                'price' => $op->price,
                'total_price' => $op->total_price,
                'special_details' => $op->special_details,
                'updated_by' => auth()->user()?->name ?? 'Shopify Review',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if ($archiveRows !== []) {
            OrderProductArchive::insert($archiveRows);
        }

        OrderProduct::query()->where('order_id', $order->id)->delete();
        foreach ($after as $row) {
            OrderProduct::create([
                'order_id' => $order->id,
                'category_id' => $row['category_id'],
                'quantity' => (string) $row['quantity'],
                'price' => $row['price'],
                'total_price' => $row['total'],
                'special_details' => $row['special_details'],
            ]);
        }

        return array_merge(['— تعديلات المنتجات —'], $productChanges);
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
        }

        foreach ($beforeByCat as $categoryId => $row) {
            if (! $afterByCat->has($categoryId)) {
                $changes[] = sprintf('حذف: %s', $row['name']);
            }
        }

        return $changes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $before
     * @param  array<int, array<string, mixed>>  $after
     */
    private function productsStructurallyEqual(array $before, array $after): bool
    {
        if (count($before) !== count($after)) {
            return false;
        }
        $beforeByCat = collect($before)->keyBy('category_id');
        foreach ($after as $row) {
            $old = $beforeByCat->get($row['category_id']);
            if ($old === null) {
                return false;
            }
            if (! $this->floatEquals($old['quantity'], $row['quantity']) || ! $this->floatEquals($old['price'], $row['price'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $changes
     */
    private function buildAutoChangeNote(array $changes): ?string
    {
        if ($changes === []) {
            return null;
        }

        return "تعديلات أثناء مراجعة Shopify:\n- ".implode("\n- ", $changes);
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
