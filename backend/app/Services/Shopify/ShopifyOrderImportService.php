<?php

namespace App\Services\Shopify;

use App\Enums\CollectionProviderType;
use App\Enums\OrderCollectionStatus;
use App\Enums\OrderSettlementStatus;
use App\Jobs\SendOrderToShippingJob;
use App\Models\Category;
use App\Models\CollectionCompany;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\OrderProduct;
use App\Models\ShopifyProductMapping;
use App\Services\Accounting\SalesOrderAccountingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ShopifyOrderImportService
{
    /**
     * كود الصنف الثابت لـ placeholder منتجات Shopify غير المطابقة.
     * البنود التي لا تطابق أي صنف ERP تُربط بهذا الصنف (بدل صنف حقيقي) ويُعلَّم الطلب «يحتاج مراجعة منتج».
     */
    public const UNMATCHED_PLACEHOLDER_ITEM_CODE = '__SHOPIFY_UNMATCHED__';

    public const UNMATCHED_PLACEHOLDER_NAME = '⚠️ منتج Shopify غير مطابق (يحتاج ربط بصنف)';

    /**
     * شركات التحصيل التي أُنشئت تلقائياً خلال جلسة المزامنة الحالية (لإظهارها للمستخدم).
     *
     * @var array<int, array{id: int, name: string, gateway: ?string}>
     */
    public array $createdCollectionCompanies = [];

    /** معرف صنف الـ placeholder (محسوب/منشأ مرة واحدة لكل دورة تشغيل). */
    private ?int $unmatchedPlaceholderCategoryId = null;

    /** true إذا احتوى آخر بناء لسطور المنتجات على بند غير مطابق (وُجِّه للـ placeholder). */
    private bool $lastBuildHadUnmatched = false;

    public function __construct(
        private ShopifyOrderShippingMethodResolver $shippingMethodResolver,
    ) {}

    /** تصفير حالة التشغيل قبل بدء مزامنة جديدة. */
    public function resetRunState(): void
    {
        $this->createdCollectionCompanies = [];
    }

    public function import(array $payload, ?string $shopDomain): Order
    {
        $shopifyOrderId = isset($payload['id']) ? (int) $payload['id'] : null;
        if (! $shopifyOrderId) {
            throw new \InvalidArgumentException('Shopify payload missing order id.');
        }

        $existing = Order::query()->where('shopify_order_id', $shopifyOrderId)->first();
        if ($existing) {
            return $this->updateExistingFromFullPayload($existing, $payload, $shopDomain);
        }

        [$orderSourceId, $defaultShippingMethodId, $fallbackCategoryId] = $this->requireShopifyOrderConfigIds();

        $lineRows = $this->buildLineItems($payload, $shopDomain, $fallbackCategoryId);
        if ($lineRows === []) {
            throw new \RuntimeException('Shopify order has no importable line items.');
        }

        $needsProductReview = $this->lastBuildHadUnmatched;

        $shippingMethodId = $this->shippingMethodResolver->resolveShippingMethodId($lineRows, $defaultShippingMethodId);

        $ctx = $this->buildOrderPayloadContext($payload, $lineRows);

        $trackingUserId = config('services.shopify.tracking_user_id');
        if ($trackingUserId === null || $trackingUserId < 1) {
            throw new \RuntimeException(
                'Shopify integration: set SHOPIFY_TRACKING_USER_ID to a valid users.id (used for order tracking history).'
            );
        }

        $order = DB::transaction(function () use (
            $shopifyOrderId,
            $shopDomain,
            $orderSourceId,
            $shippingMethodId,
            $ctx,
            $trackingUserId,
            $needsProductReview
        ) {
            $order = Order::create([
                'shopify_order_id' => $shopifyOrderId,
                'shopify_shop_domain' => $shopDomain,
                'shopify_financial_status' => $ctx['financialStatus'] !== '' ? $ctx['financialStatus'] : null,
                'shopify_fulfillment_status' => $ctx['fulfillmentStatus'] !== '' ? $ctx['fulfillmentStatus'] : null,
                'customer_name' => $ctx['customerName'],
                'customer_type' => (string) config('services.shopify.default_customer_type', 'فرد'),
                'customer_phone_1' => $ctx['phone'],
                'customer_phone_2' => '',
                'tel' => null,
                'governorate' => $ctx['governorate'],
                'city' => $ctx['city'],
                'address' => $ctx['address'],
                'order_date' => $ctx['orderDate'],
                'shipping_method_id' => $shippingMethodId,
                'order_source_id' => $orderSourceId,
                'order_status' => 'طلب جديد',
                'order_type' => (string) config('services.shopify.order_type', 'جديد'),
                'shipping_cost' => $ctx['shipping'],
                'total_invoice' => $ctx['totals']['total_invoice'],
                'prepaid_amount' => $ctx['totals']['prepaid_amount'],
                'discount' => $ctx['totals']['discount'],
                'net_total' => $ctx['totals']['net_total'],
                'vat' => $ctx['totals']['vat'],
                'sales' => 0,
                'company_id' => null,
                'bank_id' => null,
                'order_notes' => null,
                'collect_note' => $ctx['note'] !== '' ? $ctx['note'] : null,
                'reference_number' => $ctx['referenceNumber'],
                'shopify_needs_product_review' => $needsProductReview,
            ]);

            $this->insertOrderLineRows($order->id, $ctx['lineRows']);

            OrderDetails::updateOrCreate(
                ['order_id' => $order->id],
                []
            );

            $createdAt = now();
            DB::table('trackings')->insert([
                'order_id' => $order->id,
                'date' => $createdAt->toDateString(),
                'action' => $needsProductReview
                    ? 'طلب جديد (Shopify) — يحتوي منتجات غير مربوطة بأصناف، يحتاج مراجعة قبل الشحن'
                    : 'طلب جديد (Shopify)',
                'user_id' => $trackingUserId,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            Log::info('Shopify order imported', [
                'local_order_id' => $order->id,
                'shopify_order_id' => $shopifyOrderId,
                'needs_product_review' => $needsProductReview,
            ]);

            return $order;
        });

        $this->applyPaymentCollection($order, $payload);

        // لا تُرسل الطلب للشحن تلقائياً إذا احتوى منتجات غير مربوطة (صنف placeholder) — يجب ربطها أولاً.
        if (! $needsProductReview) {
            $this->dispatchShippingJobSafely($order->id);
        } else {
            Log::warning('Shopify order import: auto-ship skipped — order needs product review', [
                'order_id' => $order->id,
                'shopify_order_id' => $shopifyOrderId,
            ]);
        }

        return $order;
    }

    /**
     * تحديث الطلب الموجود من حمولة كاملة (مزامنة يدوية) دون إنشاء تكرار؛ تُحافظ على حالة الطلب المحلية (order_status).
     */
    public function updateExistingFromFullPayload(Order $order, array $payload, ?string $shopDomain): Order
    {
        [, , $fallbackCategoryId] = $this->requireShopifyOrderConfigIds();

        $lineRows = $this->buildLineItems($payload, $shopDomain, $fallbackCategoryId);
        if ($lineRows === []) {
            throw new \RuntimeException('Shopify order has no importable line items.');
        }

        $needsProductReview = $this->lastBuildHadUnmatched;

        $defaultShippingMethodId = (int) config('services.shopify.default_shipping_method_id');
        $shippingMethodId = $this->shippingMethodResolver->resolveShippingMethodId($lineRows, $defaultShippingMethodId);

        $ctx = $this->buildOrderPayloadContext($payload, $lineRows);

        $trackingUserId = config('services.shopify.tracking_user_id');
        if ($trackingUserId === null || $trackingUserId < 1) {
            throw new \RuntimeException(
                'Shopify integration: set SHOPIFY_TRACKING_USER_ID to a valid users.id (used for order tracking history).'
            );
        }

        DB::transaction(function () use ($order, $shopDomain, $ctx, $trackingUserId, $shippingMethodId, $needsProductReview) {
            $order->update([
                'shopify_needs_product_review' => $needsProductReview,
                'shopify_shop_domain' => $shopDomain ?? $order->shopify_shop_domain,
                'shopify_financial_status' => $ctx['financialStatus'] !== '' ? $ctx['financialStatus'] : null,
                'shopify_fulfillment_status' => $ctx['fulfillmentStatus'] !== '' ? $ctx['fulfillmentStatus'] : null,
                'customer_name' => $ctx['customerName'],
                'customer_phone_1' => $ctx['phone'],
                'governorate' => $ctx['governorate'],
                'city' => $ctx['city'],
                'address' => $ctx['address'],
                'order_date' => $ctx['orderDate'],
                'shipping_method_id' => $shippingMethodId,
                'shipping_cost' => $ctx['shipping'],
                'total_invoice' => $ctx['totals']['total_invoice'],
                'prepaid_amount' => $ctx['totals']['prepaid_amount'],
                'discount' => $ctx['totals']['discount'],
                'net_total' => $ctx['totals']['net_total'],
                'vat' => $ctx['totals']['vat'],
                'collect_note' => $ctx['note'] !== '' ? $ctx['note'] : null,
                'reference_number' => $ctx['referenceNumber'],
            ]);

            OrderProduct::query()->where('order_id', $order->id)->delete();
            $this->insertOrderLineRows($order->id, $ctx['lineRows']);

            OrderDetails::updateOrCreate(
                ['order_id' => $order->id],
                []
            );

            $createdAt = now();
            DB::table('trackings')->insert([
                'order_id' => $order->id,
                'date' => $createdAt->toDateString(),
                'action' => 'تحديث بيانات من Shopify (مزامنة)',
                'user_id' => $trackingUserId,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            Log::info('Shopify order refreshed from full payload', [
                'local_order_id' => $order->id,
                'shopify_order_id' => $order->shopify_order_id,
            ]);
        });

        $this->applyPaymentCollection($order->fresh() ?? $order, $payload);

        return $order->fresh() ?? $order;
    }

    private function dispatchShippingJobSafely(int $orderId): void
    {
        try {
            SendOrderToShippingJob::dispatch($orderId);
        } catch (\Throwable $e) {
            Log::warning('Shopify order import: shipping job dispatch failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public function getFallbackCategoryId(): int
    {
        [, , $fallbackCategoryId] = $this->requireShopifyOrderConfigIds();

        return $fallbackCategoryId;
    }

    /**
     * true عندما لا يوجد ربط mapping/SKU ولا مطابقة بالاسم — سيُستخدم صنف fallback.
     */
    public function isLineItemUnmatched(
        ?int $variantId,
        string $sku,
        ?string $shopDomain,
        string $title,
        string $variantTitle,
    ): bool {
        $fallbackCategoryId = $this->getFallbackCategoryId();
        $categoryId = $this->resolveCategoryId($variantId, $sku, $shopDomain, $fallbackCategoryId);

        if ($categoryId !== $fallbackCategoryId) {
            return false;
        }

        return $this->resolveByNameMatching($title, $variantTitle) === null;
    }

    private function requireShopifyOrderConfigIds(): array
    {
        $orderSourceId = (int) config('services.shopify.default_order_source_id');
        $shippingMethodId = (int) config('services.shopify.default_shipping_method_id');
        $fallbackCategoryId = (int) config('services.shopify.fallback_category_id');

        if ($orderSourceId < 1 || $shippingMethodId < 1 || $fallbackCategoryId < 1) {
            throw new \RuntimeException(
                'Shopify integration: set SHOPIFY_DEFAULT_ORDER_SOURCE_ID, SHOPIFY_DEFAULT_SHIPPING_METHOD_ID, and SHOPIFY_FALLBACK_CATEGORY_ID in .env.'
            );
        }

        if (! Category::query()->whereKey($fallbackCategoryId)->exists()) {
            throw new \RuntimeException(
                "Shopify: SHOPIFY_FALLBACK_CATEGORY_ID={$fallbackCategoryId} غير موجود في جدول categories."
            );
        }

        return [$orderSourceId, $shippingMethodId, $fallbackCategoryId];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineRows
     * @return array<string, mixed>
     */
    private function buildOrderPayloadContext(array $payload, array $lineRows): array
    {
        $shipping = $this->extractShippingCost($payload);
        $totals = $this->extractTotals($payload);

        $shippingAddr = $payload['shipping_address'] ?? [];
        $billingAddr = $payload['billing_address'] ?? [];
        $addr = $shippingAddr !== [] ? $shippingAddr : $billingAddr;

        $customerName = $this->buildCustomerName($addr, $payload);
        $phone = $this->resolvePhone($payload, $addr);
        if ($phone === '') {
            $phone = (string) config('services.shopify.placeholder_phone', '0000000000');
        }

        $financialStatus = (string) ($payload['financial_status'] ?? '');
        $fulfillmentStatus = (string) ($payload['fulfillment_status'] ?? '');

        $governorate = (string) ($addr['province'] ?? $addr['city'] ?? config('services.shopify.default_governorate', 'غير محدد'));
        $city = (string) ($addr['city'] ?? '');
        $address = trim(implode(' ', array_filter([
            $addr['address1'] ?? '',
            $addr['address2'] ?? '',
        ])));

        if ($address === '') {
            $address = (string) config('services.shopify.default_address', '-');
        }

        $orderDate = isset($payload['created_at'])
            ? Carbon::parse($payload['created_at'])->toDateString()
            : now()->toDateString();

        $referenceNumber = isset($payload['name']) ? (string) $payload['name'] : null;
        $currency = (string) ($payload['currency'] ?? '');
        $note = trim(sprintf(
            'Shopify %s | Order #%s | %s',
            $referenceNumber ?? '',
            $payload['order_number'] ?? '',
            $currency !== '' ? "Currency: {$currency}" : ''
        ));

        return [
            'lineRows' => $lineRows,
            'shipping' => $shipping,
            'totals' => $totals,
            'customerName' => $customerName,
            'phone' => $phone,
            'financialStatus' => $financialStatus,
            'fulfillmentStatus' => $fulfillmentStatus,
            'governorate' => $governorate,
            'city' => $city,
            'address' => $address,
            'orderDate' => $orderDate,
            'referenceNumber' => $referenceNumber,
            'note' => $note,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineRows
     */
    private function insertOrderLineRows(int $orderId, array $lineRows): void
    {
        $now = now();
        foreach ($lineRows as &$row) {
            $row['order_id'] = $orderId;
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }
        unset($row);
        OrderProduct::insert($lineRows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildLineItems(array $payload, ?string $shopDomain, int $fallbackCategoryId): array
    {
        $this->lastBuildHadUnmatched = false;
        $placeholderCategoryId = $this->resolveUnmatchedPlaceholderCategoryId($fallbackCategoryId);

        $rows = [];
        foreach ($payload['line_items'] ?? [] as $line) {
            if (! empty($line['gift_card'])) {
                continue;
            }

            $variantId = isset($line['variant_id']) ? (int) $line['variant_id'] : null;
            $sku = isset($line['sku']) ? trim((string) $line['sku']) : '';
            $title = (string) ($line['title'] ?? '');
            $variantTitle = (string) ($line['variant_title'] ?? '');

            $categoryId = $this->resolveCategoryId($variantId, $sku, $shopDomain, $fallbackCategoryId);
            $isUnmatched = false;

            if ($categoryId === $fallbackCategoryId) {
                $matchedId = $this->resolveByNameMatching($title, $variantTitle);
                if ($matchedId !== null) {
                    $categoryId = $this->ensureValidCategoryId($matchedId, $fallbackCategoryId);

                    if ($variantId) {
                        ShopifyProductMapping::query()->updateOrCreate(
                            ['shopify_variant_id' => $variantId],
                            [
                                'shop_domain' => $shopDomain,
                                'sku' => $sku !== '' ? $sku : null,
                                'category_id' => $categoryId,
                                'active' => true,
                            ]
                        );
                    }
                } else {
                    // لا يوجد ربط ولا مطابقة بالاسم → وجّه البند لصنف placeholder (بدل صنف حقيقي)
                    // واعلِم الطلب أنه يحتاج مراجعة منتج قبل الشحن.
                    $categoryId = $placeholderCategoryId;
                    $isUnmatched = true;
                    $this->lastBuildHadUnmatched = true;
                }
            }

            $qty = (int) ($line['quantity'] ?? 0);
            if ($qty < 1) {
                continue;
            }

            $unitPrice = (float) ($line['price'] ?? 0);
            $special = trim($title.($variantTitle !== '' && $variantTitle !== 'Default Title' ? " — {$variantTitle}" : ''));

            $rows[] = [
                'category_id' => $isUnmatched
                    ? $placeholderCategoryId
                    : $this->ensureValidCategoryId($categoryId, $fallbackCategoryId),
                'shopify_line_item_id' => isset($line['id']) ? (int) $line['id'] : null,
                'shopify_variant_id' => $variantId,
                'quantity' => (string) $qty,
                'price' => $unitPrice,
                'total_price' => round($unitPrice * $qty, 2),
                'special_details' => $special,
            ];
        }

        return $rows;
    }

    /**
     * يحل (أو يُنشئ) صنف placeholder غير قابل للبيع لاستقبال بنود Shopify غير المطابقة.
     * في حال تعذّر إنشاؤه (لا وحدة قياس/خط إنتاج) يُستخدم الصنف الافتراضي القديم لتفادي كسر السحب.
     */
    public function unmatchedPlaceholderCategoryId(?int $fallbackCategoryId = null): int
    {
        return $this->resolveUnmatchedPlaceholderCategoryId(
            $fallbackCategoryId ?? $this->getFallbackCategoryId()
        );
    }

    private function resolveUnmatchedPlaceholderCategoryId(int $fallbackCategoryId): int
    {
        if ($this->unmatchedPlaceholderCategoryId !== null) {
            return $this->unmatchedPlaceholderCategoryId;
        }

        $existing = Category::query()
            ->where('item_code', self::UNMATCHED_PLACEHOLDER_ITEM_CODE)
            ->first();
        if ($existing) {
            return $this->unmatchedPlaceholderCategoryId = (int) $existing->id;
        }

        $measurementId = (int) (DB::table('measurements')->orderBy('id')->value('id') ?? 0);
        $productionId = (int) (DB::table('productions')->min('id') ?? 0);

        if ($measurementId < 1 || $productionId < 1) {
            Log::warning('Shopify import: cannot create unmatched placeholder category, using legacy fallback', [
                'measurement_id' => $measurementId,
                'production_id' => $productionId,
                'fallback_category_id' => $fallbackCategoryId,
            ]);

            return $this->unmatchedPlaceholderCategoryId = $fallbackCategoryId;
        }

        try {
            $stockId = DB::table('stocks')
                ->where('name', 'like', '%منتج تام%')
                ->orWhere('name', 'like', '%تام%')
                ->value('id');
            $now = now();
            $id = DB::table('categories')->insertGetId([
                'category_name' => self::UNMATCHED_PLACEHOLDER_NAME,
                'category_price' => 0,
                'unit_price' => 0,
                'total_price' => 0,
                'sell_total_price' => 0,
                'initial_balance' => 0,
                'minimum_quantity' => 0,
                'warehouse' => 'مخزن منتج تام',
                'production_id' => $productionId,
                'item_code' => self::UNMATCHED_PLACEHOLDER_ITEM_CODE,
                'stock_id' => $stockId ? (int) $stockId : null,
                'measurement_id' => $measurementId,
                'category_image' => 'no-image.png',
                'product_type' => 'finished',
                'status' => '1',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            Log::info('Shopify import: unmatched placeholder category created', ['category_id' => $id]);

            return $this->unmatchedPlaceholderCategoryId = (int) $id;
        } catch (\Throwable $e) {
            Log::warning('Shopify import: failed creating unmatched placeholder category, using legacy fallback', [
                'error' => $e->getMessage(),
                'fallback_category_id' => $fallbackCategoryId,
            ]);

            return $this->unmatchedPlaceholderCategoryId = $fallbackCategoryId;
        }
    }

    /**
     * Tries to match a Shopify product name to an ERP category using the same normalization logic.
     * يُفوِّض المطابقة لخدمة الربط (مصدر واحد للقواعد) حتى تتطابق صيغ مثل
     * «Forest and footrest» مع «Forest - Footrest» دون إنشاء صنف مكرر.
     */
    private function resolveByNameMatching(string $title, string $variantTitle): ?int
    {
        $variantLabel = $variantTitle !== '' && strtolower($variantTitle) !== 'default title'
            ? $variantTitle
            : '';

        $syncService = $this->mappingSyncService();
        $normalizedName = $syncService->normalizeShopifyNameToErp($title, $variantLabel);

        if ($normalizedName === '') {
            return null;
        }

        return $syncService->findCategoryByNormalizedName($normalizedName)?->id;
    }

    private ?ShopifyProductMappingSyncService $mappingSyncServiceInstance = null;

    private function mappingSyncService(): ShopifyProductMappingSyncService
    {
        return $this->mappingSyncServiceInstance ??= app(ShopifyProductMappingSyncService::class);
    }

    private function resolveCategoryId(?int $variantId, string $sku, ?string $shopDomain, int $fallbackCategoryId): int
    {
        $q = ShopifyProductMapping::query()->where('active', true);

        if ($shopDomain) {
            $q->where(function ($sub) use ($shopDomain) {
                $sub->whereNull('shop_domain')->orWhere('shop_domain', $shopDomain);
            });
        }

        if ($variantId) {
            $byVariant = (clone $q)->where('shopify_variant_id', $variantId)->first();
            if ($byVariant) {
                return $this->ensureValidCategoryId((int) $byVariant->category_id, $fallbackCategoryId);
            }
        }

        if ($sku !== '') {
            $bySku = (clone $q)->where('sku', $sku)->first();
            if ($bySku) {
                return $this->ensureValidCategoryId((int) $bySku->category_id, $fallbackCategoryId);
            }
        }

        return $fallbackCategoryId;
    }

    private function ensureValidCategoryId(int $categoryId, int $fallbackCategoryId): int
    {
        if ($categoryId > 0 && Category::query()->whereKey($categoryId)->exists()) {
            return $categoryId;
        }

        if ($categoryId !== $fallbackCategoryId && $categoryId > 0) {
            Log::warning('Shopify import: mapping category_id missing, using fallback', [
                'requested_category_id' => $categoryId,
                'fallback_category_id' => $fallbackCategoryId,
            ]);
        }

        return $fallbackCategoryId;
    }

    private function extractShippingCost(array $payload): float
    {
        $sum = 0.0;
        foreach ($payload['shipping_lines'] ?? [] as $line) {
            if (isset($line['price_set']['shop_money']['amount'])) {
                $sum += (float) $line['price_set']['shop_money']['amount'];
            } elseif (isset($line['discounted_price_set']['shop_money']['amount'])) {
                $sum += (float) $line['discounted_price_set']['shop_money']['amount'];
            } elseif (isset($line['price'])) {
                $sum += (float) $line['price'];
            }
        }

        if ($sum > 0) {
            return round($sum, 2);
        }

        if (isset($payload['total_shipping_price_set']['shop_money']['amount'])) {
            return round((float) $payload['total_shipping_price_set']['shop_money']['amount'], 2);
        }

        return 0.0;
    }

    /**
     * @return array{total_invoice: float, discount: float, vat: float, net_total: float, prepaid_amount: float}
     */
    private function extractTotals(array $payload): array
    {
        $totalLineItems = (float) ($payload['total_line_items_price'] ?? 0);
        $subtotal = (float) ($payload['subtotal_price'] ?? $totalLineItems);
        $discount = (float) ($payload['total_discounts'] ?? 0);
        $tax = (float) ($payload['total_tax'] ?? 0);
        $shipping = $this->extractShippingCost($payload);
        $shopifyTotal = (float) ($payload['total_price'] ?? 0);

        $productsBase = $totalLineItems > 0 ? $totalLineItems : $subtotal;
        $totalInvoice = round($productsBase + $shipping + $tax, 2);

        $prepaid = $this->resolvePrepaidAmount($payload, $shopifyTotal);
        // المتبقي للتحصيل عند التسليم = إجمالي Shopify − المدفوع مسبقاً
        $netTotal = round(max(0, $shopifyTotal - $prepaid), 2);

        return [
            'total_invoice' => $totalInvoice,
            'discount' => round($discount, 2),
            'vat' => round($tax, 2),
            'net_total' => $netTotal,
            'prepaid_amount' => round($prepaid, 2),
        ];
    }

    /**
     * المبلغ المحصّل مسبقاً على Shopify (أونلاين / جزئي). الباقي يُسجَّل في net_total.
     */
    private function resolvePrepaidAmount(array $payload, float $shopifyTotal): float
    {
        if ($shopifyTotal <= 0) {
            return 0.0;
        }

        if (array_key_exists('total_outstanding', $payload) && $payload['total_outstanding'] !== null && $payload['total_outstanding'] !== '') {
            $outstanding = max(0, (float) $payload['total_outstanding']);

            return round(max(0, $shopifyTotal - $outstanding), 2);
        }

        $status = strtolower((string) ($payload['financial_status'] ?? 'pending'));

        return match ($status) {
            'paid' => round($shopifyTotal, 2),
            default => 0.0,
        };
    }

    /**
     * @param  array<string, mixed>  $addr
     */
    private function buildCustomerName(array $addr, array $payload): string
    {
        $first = trim((string) ($addr['first_name'] ?? ''));
        $last = trim((string) ($addr['last_name'] ?? ''));
        $name = trim($first.' '.$last);
        if ($name !== '') {
            return $name;
        }
        if (! empty($payload['customer']['first_name']) || ! empty($payload['customer']['last_name'])) {
            return trim(
                ($payload['customer']['first_name'] ?? '').' '.($payload['customer']['last_name'] ?? '')
            );
        }

        return (string) config('services.shopify.default_customer_name', 'عميل Shopify');
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // تصحيح خطأ شائع: 21010… بدل 2010…
        if (str_starts_with($digits, '210') && strlen($digits) >= 12) {
            $digits = '20'.substr($digits, 2);
        }

        // مصر دولي: 20 + 10 أرقام محلية → 01xxxxxxxxx
        if (str_starts_with($digits, '20') && strlen($digits) === 12) {
            return '0'.substr($digits, 2);
        }

        if (str_starts_with($digits, '200') && strlen($digits) === 13) {
            return substr($digits, 2);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return $digits;
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '1')) {
            return '0'.$digits;
        }

        return $digits;
    }

    private function isUsablePhone(string $normalized): bool
    {
        if ($normalized === '') {
            return false;
        }

        if (preg_match('/^0+$/', $normalized)) {
            return false;
        }

        return strlen($normalized) >= 9;
    }

    private function isPhoneNoteAttributeName(string $name): bool
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return false;
        }

        $lower = strtolower($trimmed);
        if (in_array($lower, [
            'phone',
            'mobile',
            'tel',
            'telephone',
            'phone_number',
            'customer_phone',
            'mobile_number',
            'contact_phone',
        ], true)) {
            return true;
        }

        $arabicHints = ['رقم الهاتف', 'رقم الموبايل', 'رقم التليفون', 'موبايل', 'هاتف', 'تليفون'];
        foreach ($arabicHints as $hint) {
            if (mb_strpos($trimmed, $hint) !== false) {
                return true;
            }
        }

        return preg_match('/phone|mobile|tel/i', $trimmed) === 1;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $addr
     */
    private function resolvePhone(array $payload, array $addr): string
    {
        $candidates = [];

        foreach ($payload['note_attributes'] ?? [] as $na) {
            if (! is_array($na)) {
                continue;
            }
            if ($this->isPhoneNoteAttributeName((string) ($na['name'] ?? ''))) {
                $candidates[] = (string) ($na['value'] ?? '');
            }
        }

        $candidates = array_merge($candidates, [
            (string) data_get($payload, 'shipping_address.phone'),
            (string) ($addr['phone'] ?? ''),
            (string) ($payload['phone'] ?? ''),
            (string) data_get($payload, 'customer.phone'),
            (string) data_get($payload, 'billing_address.phone'),
            (string) data_get($payload, 'customer.default_address.phone'),
        ]);

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizePhone($candidate);
            if ($this->isUsablePhone($normalized)) {
                return $normalized;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function syncFromUpdate(array $payload, ?string $shopDomain): void
    {
        $shopifyOrderId = isset($payload['id']) ? (int) $payload['id'] : null;
        if (! $shopifyOrderId) {
            return;
        }

        $order = Order::query()->where('shopify_order_id', $shopifyOrderId)->first();
        if (! $order) {
            Log::info('Shopify orders/updated: no local order', ['shopify_order_id' => $shopifyOrderId]);

            return;
        }

        $this->applyFinancialFieldsFromPayload($order, $payload, $shopDomain);

        Log::info('Shopify order updated from webhook', ['local_order_id' => $order->id]);
    }


    /**
     * تحديث حقول المبالغ فقط (بدون إعادة بناء سطور المنتجات) — للإصلاح أو webhook.
     */
    public function applyFinancialFieldsFromPayload(Order $order, array $payload, ?string $shopDomain = null): Order
    {
        $totals = $this->extractTotals($payload);
        $shipping = $this->extractShippingCost($payload);
        $financialStatus = (string) ($payload['financial_status'] ?? '');
        $fulfillmentStatus = (string) ($payload['fulfillment_status'] ?? '');

        $order->update(array_filter([
            'shopify_shop_domain' => $shopDomain ?? $order->shopify_shop_domain,
            'shopify_financial_status' => $financialStatus !== '' ? $financialStatus : null,
            'shopify_fulfillment_status' => $fulfillmentStatus !== '' ? $fulfillmentStatus : null,
            'shipping_cost' => $shipping,
            'total_invoice' => $totals['total_invoice'],
            'prepaid_amount' => $totals['prepaid_amount'],
            'discount' => $totals['discount'],
            'net_total' => $totals['net_total'],
            'vat' => $totals['vat'],
        ], fn ($v) => $v !== null));

        $this->applyPaymentCollection($order->fresh() ?? $order, $payload);

        return $order->fresh() ?? $order;
    }

    /**
     * يربط الطلب المدفوع من Shopify بشركة التحصيل المطابقة لوسيلة الدفع،
     * ويعيد بناء قيود الإثبات بحيث تقع مديونية المبلغ المدفوع على ذمم تلك الشركة.
     *
     * - يُنفَّذ فقط عندما يوجد مبلغ مدفوع مقدماً (prepaid_amount > 0) أي الطلب مدفوع كلياً/جزئياً.
     * - لا يُسجَّل قيد نقدية للدفعة (النقدية ما تزال لدى شركة التحصيل) فتبقى مديونية مفتوحة عليها.
     */
    private function applyPaymentCollection(Order $order, array $payload): void
    {
        $prepaid = (float) ($order->prepaid_amount ?? 0);
        if ($prepaid <= 0.009) {
            return;
        }

        try {
            $resolver = app(ShopifyPaymentCollectionResolver::class);
            $result = $resolver->resolveOrCreateForPayload($payload);
            if (! $result || ! ($result['company'] instanceof CollectionCompany)) {
                return;
            }

            /** @var CollectionCompany $company */
            $company = $result['company'];
            $gatewayLabel = $result['gateway_label'] ?? $result['canonical'];

            if (Schema::hasColumn('orders', 'shopify_payment_gateway')) {
                $order->forceFill(['shopify_payment_gateway' => $gatewayLabel])->saveQuietly();
            }

            $od = OrderDetails::firstOrCreate(['order_id' => $order->id]);
            $od->collection_provider_type = CollectionProviderType::CollectionCompany->value;
            $od->collection_provider_id = $company->id;
            if ($company->linked_shipping_company_id) {
                $od->collection_company_id = $company->linked_shipping_company_id;
            }

            // المبلغ المحصّل إلكترونياً مديونية على شركة التحصيل حتى تُسوّيه للنظام،
            // ويظهر فوراً في تقرير ذمم شركات التحصيل (لا يتوقف على شحن/تسليم الطلب).
            $od->collection_receivable_amount = round($prepaid, 3);
            if (! in_array($od->collection_status, [
                OrderCollectionStatus::Collected->value,
                OrderCollectionStatus::Transferred->value,
                OrderCollectionStatus::Refused->value,
                OrderCollectionStatus::Partial->value,
            ], true)) {
                $od->collection_status = OrderCollectionStatus::Pending->value;
            }
            if (! $od->settlement_status || $od->settlement_status === OrderSettlementStatus::NotApplicable->value) {
                $od->settlement_status = OrderSettlementStatus::Open->value;
            }

            $od->save();

            $this->recordPaymentCollectionTrail($order, $company, (string) $gatewayLabel, (bool) $result['created']);

            $fresh = Order::with(['order_products', 'order_details'])->find($order->id);
            if ($fresh) {
                app(SalesOrderAccountingService::class)->refreshOrderRecognition($fresh, rebuildPrepaid: true);
            }
        } catch (\Throwable $e) {
            Log::error('Shopify payment collection mapping failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * يسجّل أثر وسيلة الدفع وشركة التحصيل في تتبّع الطلب وملاحظاته (ليُعرف داخل تفاصيل الطلب)،
     * ويجمّع الشركات المُنشأة تلقائياً لإظهارها في نتيجة المزامنة.
     */
    private function recordPaymentCollectionTrail(Order $order, CollectionCompany $company, string $gatewayLabel, bool $created): void
    {
        $trackingUserId = config('services.shopify.tracking_user_id');
        $now = now();

        $message = sprintf(
            'وسيلة الدفع (Shopify): %s — التحصيل على شركة «%s» (مديونية على حسابها حتى التسوية)',
            $gatewayLabel !== '' ? $gatewayLabel : 'غير محدد',
            $company->name
        );
        if ($created) {
            $message .= ' — تم إنشاء شركة التحصيل وحساب ذممها تلقائياً';

            $this->createdCollectionCompanies[$company->id] = [
                'id' => (int) $company->id,
                'name' => (string) $company->name,
                'gateway' => $gatewayLabel !== '' ? $gatewayLabel : null,
            ];

            Log::info('Shopify: collection company created automatically from payment gateway', [
                'collection_company_id' => $company->id,
                'name' => $company->name,
                'gateway' => $gatewayLabel,
                'order_id' => $order->id,
                'receivable_tree_account_id' => $company->receivable_tree_account_id,
            ]);
        }

        if ($trackingUserId !== null && (int) $trackingUserId > 0) {
            try {
                DB::table('trackings')->insert([
                    'order_id' => $order->id,
                    'date' => $now->toDateString(),
                    'action' => $message,
                    'user_id' => (int) $trackingUserId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('notes')->insert([
                    'order_id' => $order->id,
                    'user_id' => (int) $trackingUserId,
                    'note' => $message,
                    'added_from' => 'مزامنة Shopify',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Shopify payment collection trail logging failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
