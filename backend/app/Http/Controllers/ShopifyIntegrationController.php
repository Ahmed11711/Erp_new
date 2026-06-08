<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Item;
use App\Models\Measurement;
use App\Models\ShopifyProductMapping;
use App\Models\Stock;
use App\Services\Items\ItemCodeService;
use App\Services\Shopify\ShopifyAdminApiClient;
use App\Services\Shopify\ShopifyIntegrationSettingsService;
use App\Services\Shopify\ShopifyOrdersSyncService;
use App\Services\Shopify\ShopifyProductMappingSyncService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopifyIntegrationController extends Controller
{
    /**
     * إعدادات تكامل Shopify (قابلة للتعديل من الواجهة).
     */
    public function integrationSettings(ShopifyIntegrationSettingsService $settingsService)
    {
        return response()->json($settingsService->toArray());
    }

    /**
     * تحديث إعدادات تكامل Shopify.
     *
     * Body: { auto_import_orders: bool }
     */
    public function updateIntegrationSettings(Request $request, ShopifyIntegrationSettingsService $settingsService)
    {
        $request->validate([
            'auto_import_orders' => 'required|boolean',
        ]);

        $settingsService->setAutoImportOrders((bool) $request->boolean('auto_import_orders'));

        return response()->json([
            'success' => true,
            'message' => $request->boolean('auto_import_orders')
                ? 'تم تفعيل استيراد الطلبات تلقائياً عبر Webhooks.'
                : 'تم إيقاف الاستيراد التلقائي؛ استخدم مزامنة الطلبات اليدوية فقط.',
            ...$settingsService->toArray(),
        ]);
    }

    /**
     * التحقق من الاتصال بـ Shopify Admin API (قراءة بيانات المتجر).
     */
    public function status()
    {
        try {
            $client = ShopifyAdminApiClient::fromConfig();
            $response = $client->get('shop.json');
            if (! $response->successful()) {
                return response()->json([
                    'ok' => false,
                    'error' => 'HTTP '.$response->status(),
                    'body' => $response->body(),
                ], 422);
            }

            return response()->json([
                'ok' => true,
                'shop' => $response->json('shop'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * مزامنة متغيرات المنتجات من Shopify إلى جدول الربط المحلي.
     *
     * Body (اختياري):
     * - date_from / date_to: نطاق حسب تاريخ إنشاء المنتج في Shopify (created_at).
     * - days: عند عدم إرسال التاريخين، حدّ created_at_min = الآن − N يوماً؛ عدم الإرسال = جلب كل المنتجات (قد يكون بطيئاً).
     */
    public function syncProductMappings(Request $request, ShopifyProductMappingSyncService $syncService)
    {
        $dateFromStr = $request->input('date_from');
        $dateToStr = $request->input('date_to');
        $hasFrom = $dateFromStr !== null && $dateFromStr !== '';
        $hasTo = $dateToStr !== null && $dateToStr !== '';

        if ($hasFrom !== $hasTo) {
            return response()->json([
                'success' => false,
                'message' => 'أرسل تاريخ البداية والنهاية معاً (date_from و date_to) أو اتركهما فارغين.',
            ], 422);
        }

        try {
            if ($hasFrom && $hasTo) {
                try {
                    $dateFrom = Carbon::parse((string) $dateFromStr)->startOfDay();
                    $dateTo = Carbon::parse((string) $dateToStr)->startOfDay();
                } catch (\Throwable) {
                    return response()->json([
                        'success' => false,
                        'message' => 'صيغة التاريخ غير صالحة. استخدم مثلاً YYYY-MM-DD.',
                    ], 422);
                }
                $result = $syncService->sync($dateFrom, $dateTo);
            } else {
                $hasDaysKey = $request->exists('days');
                if (! $hasDaysKey) {
                    $result = $syncService->sync();
                } else {
                    $days = $request->input('days');
                    $days = is_numeric($days) ? (int) $days : 0;
                    if ($days < 0) {
                        $days = 0;
                    }
                    if ($days > 3650) {
                        $days = 3650;
                    }
                    $result = $days === 0
                        ? $syncService->sync()
                        : $syncService->sync(null, null, $days);
                }
            }

            return response()->json(array_merge([
                'success' => true,
                'message' => 'تمت مزامنة متغيرات المنتجات.',
            ], $result));
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * عرض سجلات الربط (للمراجعة بعد المزامنة أو التعديل اليدوي في قاعدة البيانات).
     */
    public function productMappingsIndex(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 200);

        return ShopifyProductMapping::query()
            ->with(['category:id,category_name'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * معاينة سحب الطلبات: جلب من Shopify + اكتشاف منتجات غير مربوطة بأصناف ERP.
     */
    public function previewOrderSync(Request $request, ShopifyOrdersSyncService $syncService)
    {
        try {
            [$days, $dateFrom, $dateTo] = $this->resolveShopifySyncWindow($request);
            $result = $dateFrom !== null && $dateTo !== null
                ? $syncService->preview(0, $dateFrom, $dateTo)
                : $syncService->preview($days);

            return response()->json(array_merge([
                'success' => true,
                'message' => count($result['unmatched_products'] ?? []) > 0
                    ? 'وُجدت منتجات غير مربوطة — راجعها قبل إكمال السحب.'
                    : 'لا توجد منتجات غير مربوطة — يمكن متابعة الاستيراد.',
            ], $result));
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * استيراد طلبات Shopify: الجديد فقط؛ الموجودة مسبقاً (shopify_order_id) تُتخطى دون تعديل.
     *
     * Body:
     * - date_from / date_to: نطاق تاريخ الإنشاء (YYYY-MM-DD أو ISO) — يُفضّل على `days`.
     * - days: عدد الأيام للخلف من الآن عند عدم إرسال التاريخين (افتراضي 30)، 0 = كل الطلبات بدون حد أدنى.
     */
    public function syncOrders(Request $request, ShopifyOrdersSyncService $syncService)
    {
        if ($request->filled('preview_token')) {
            try {
                $result = $syncService->importFromPreviewToken((string) $request->input('preview_token'));

                return response()->json(array_merge([
                    'success' => true,
                    'message' => 'تمت معالجة مزامنة الطلبات.',
                ], $result));
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
        }

        try {
            [$days, $dateFrom, $dateTo] = $this->resolveShopifySyncWindow($request);
            $result = $dateFrom !== null && $dateTo !== null
                ? $syncService->sync(0, $dateFrom, $dateTo)
                : $syncService->sync($days);

            return response()->json(array_merge([
                'success' => true,
                'message' => 'تمت معالجة مزامنة الطلبات.',
            ], $result));
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * @return array{0: int, 1: ?Carbon, 2: ?Carbon}
     */
    private function resolveShopifySyncWindow(Request $request): array
    {
        $dateFromStr = $request->input('date_from');
        $dateToStr = $request->input('date_to');
        $hasFrom = $dateFromStr !== null && $dateFromStr !== '';
        $hasTo = $dateToStr !== null && $dateToStr !== '';

        if ($hasFrom !== $hasTo) {
            throw new \InvalidArgumentException('أرسل تاريخ البداية والنهاية معاً (date_from و date_to) أو اتركهما فارغين لاستخدام عدد الأيام.');
        }

        if ($hasFrom && $hasTo) {
            try {
                $dateFrom = Carbon::parse((string) $dateFromStr)->startOfDay();
                $dateTo = Carbon::parse((string) $dateToStr)->startOfDay();
            } catch (\Throwable) {
                throw new \InvalidArgumentException('صيغة التاريخ غير صالحة. استخدم مثلاً YYYY-MM-DD.');
            }

            return [0, $dateFrom, $dateTo];
        }

        $days = $request->input('days', 30);
        $days = is_numeric($days) ? (int) $days : 30;
        if ($days < 0) {
            $days = 0;
        }
        if ($days > 3650) {
            $days = 3650;
        }

        return [$days, null, null];
    }

    /**
     * تحديث category_id لسجل ربط mapping محدد (لربط منتج Shopify بصنف ERP يدوياً).
     */
    public function updateMappingCategory(Request $request, int $id)
    {
        $request->validate([
            'category_id' => 'required|integer|exists:categories,id',
        ]);

        $mapping = ShopifyProductMapping::findOrFail($id);
        $mapping->update(['category_id' => $request->input('category_id')]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث ربط المنتج بالصنف.',
            'mapping' => $mapping->load('category:id,category_name'),
        ]);
    }

    /**
     * إنشاء أصناف جديدة في ERP للمنتجات غير المطابقة من Shopify وربطها تلقائياً.
     *
     * Body:
     * - products: array of {
     *     shopify_variant_id, name, price (sell), sku?,
     *     category_price? (cost), measurement_id?, stock_id?, shopify_product_id?
     *   }
     * - stock_id: مخزن افتراضي عند عدم تحديد stock_id لكل منتج
     * - shop_domain: اختياري لربط mapping
     */
    public function createUnmatchedCategories(Request $request)
    {
        $request->validate([
            'products' => 'required|array|min:1',
            'products.*.shopify_variant_id' => 'required|integer',
            'products.*.name' => 'required|string|max:255',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.sku' => 'nullable|string|max:100',
            'products.*.category_price' => 'nullable|numeric|min:0',
            'products.*.measurement_id' => 'nullable|integer|exists:measurements,id',
            'products.*.stock_id' => 'nullable|integer|exists:stocks,id',
            'products.*.shopify_product_id' => 'nullable|integer',
            'stock_id' => 'nullable|integer|exists:stocks,id',
            'shop_domain' => 'nullable|string|max:255',
        ]);

        $defaultStockId = $this->resolveDefaultFinishedGoodsStockId($request->input('stock_id'));
        $defaultMeasurementId = Measurement::query()->orderBy('id')->value('id');
        $defaultProductionId = (int) (DB::table('productions')->min('id') ?? 0);
        $shopDomain = $request->input('shop_domain');
        $mappingSync = app(ShopifyProductMappingSyncService::class);

        if ($defaultProductionId < 1) {
            return response()->json([
                'success' => false,
                'message' => 'لا يوجد خط إنتاج في النظام — أضف خط إنتاج من التصنيع أولاً.',
                'created' => [],
                'errors' => [],
            ], 422);
        }

        if (! $defaultMeasurementId) {
            return response()->json([
                'success' => false,
                'message' => 'لا توجد وحدة قياس في النظام — أضف وحدة قياس أولاً.',
                'created' => [],
                'errors' => [],
            ], 422);
        }

        $created = [];
        $errors = [];
        // أصناف أُنشئت ضمن نفس الدفعة (مفتاح مقارنة → صنف) لتفادي التكرار داخل نفس الطلب.
        $batchByKey = [];

        DB::transaction(function () use ($request, $defaultStockId, $defaultMeasurementId, $defaultProductionId, $shopDomain, $mappingSync, &$created, &$errors, &$batchByKey) {
            foreach ($request->input('products') as $product) {
                $variantId = (int) $product['shopify_variant_id'];
                $name = trim($product['name']);
                $sellPrice = round((float) $product['price'], 2);
                $costPrice = isset($product['category_price'])
                    ? round((float) $product['category_price'], 2)
                    : $sellPrice;
                $sku = isset($product['sku']) ? trim((string) $product['sku']) : null;
                $stockId = isset($product['stock_id']) ? (int) $product['stock_id'] : $defaultStockId;
                $measurementId = isset($product['measurement_id'])
                    ? (int) $product['measurement_id']
                    : ($defaultMeasurementId ? (int) $defaultMeasurementId : null);
                $shopifyProductId = isset($product['shopify_product_id']) ? (int) $product['shopify_product_id'] : null;
                $warehouse = $this->resolveWarehouseNameForStock($stockId);
                $itemCode = ($sku !== null && $sku !== '' && ! Category::query()->where('item_code', $sku)->exists())
                    ? $sku
                    : null;

                if (! $measurementId) {
                    $errors[] = [
                        'shopify_variant_id' => $variantId,
                        'name' => $name,
                        'error' => 'لا توجد وحدة قياس في النظام — أضف وحدة أولاً.',
                    ];
                    continue;
                }

                // مطابقة موحّدة بالاسم لتفادي التكرار رغم اختلاف الفواصل
                // (مثل «Forest and footrest» مقابل «Forest - Footrest»).
                $comparisonKey = $mappingSync->comparisonKeyForName($name);

                $existingCategory = $batchByKey[$comparisonKey]
                    ?? $mappingSync->findCategoryByNormalizedName($name);

                if ($existingCategory) {
                    $this->upsertShopifyMapping(
                        $variantId,
                        (int) $existingCategory->id,
                        $sku,
                        $shopDomain,
                        $shopifyProductId
                    );
                    if ($comparisonKey !== '') {
                        $batchByKey[$comparisonKey] = $existingCategory;
                    }
                    $created[] = [
                        'shopify_variant_id' => $variantId,
                        'category_id' => $existingCategory->id,
                        'category_name' => $existingCategory->category_name,
                        'action' => 'linked_existing',
                    ];
                    continue;
                }

                try {
                    $category = $this->createShopifyCategoryRecord(
                        $name,
                        $costPrice,
                        $sellPrice,
                        $warehouse,
                        $defaultProductionId,
                        $itemCode,
                        $stockId,
                        $measurementId
                    );

                    if ($category->item_code === null || $category->item_code === '') {
                        app(ItemCodeService::class)->ensureCode(Item::query()->findOrFail($category->id));
                        $category->refresh();
                    }

                    $this->upsertShopifyMapping(
                        $variantId,
                        (int) $category->id,
                        $sku,
                        $shopDomain,
                        $shopifyProductId
                    );

                    if ($comparisonKey !== '') {
                        $batchByKey[$comparisonKey] = $category;
                    }

                    $created[] = [
                        'shopify_variant_id' => $variantId,
                        'category_id' => $category->id,
                        'category_name' => $name,
                        'action' => 'created_new',
                    ];
                } catch (\Throwable $e) {
                    $errors[] = [
                        'shopify_variant_id' => $variantId,
                        'name' => $name,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        });

        $createdCount = count($created);
        $message = $createdCount > 0
            ? sprintf('تم إنشاء/ربط %d صنف.', $createdCount)
            : (count($errors) > 0
                ? 'لم يُنشأ أي صنف: '.($errors[0]['error'] ?? 'خطأ غير معروف')
                : 'لم يُنشأ أي صنف.');

        return response()->json([
            'success' => $createdCount > 0,
            'message' => $message,
            'created' => $created,
            'errors' => $errors,
        ], $createdCount > 0 ? 200 : 422);
    }

    private function createShopifyCategoryRecord(
        string $name,
        float $costPrice,
        float $sellPrice,
        string $warehouse,
        int $productionId,
        ?string $itemCode,
        ?int $stockId,
        int $measurementId,
    ): Category {
        $now = now();

        // إدراج صريح يضمن category_image (Eloquent قد يحذف الحقول الفارغة من INSERT).
        $id = DB::table('categories')->insertGetId([
            'category_name' => $name,
            'category_price' => $costPrice,
            'unit_price' => $costPrice,
            'total_price' => $costPrice,
            'sell_total_price' => $sellPrice,
            'initial_balance' => 0,
            'minimum_quantity' => 0,
            'warehouse' => $warehouse,
            'production_id' => $productionId,
            'item_code' => $itemCode,
            'stock_id' => $stockId,
            'measurement_id' => $measurementId,
            'category_image' => 'no-image.png',
            'product_type' => 'finished',
            'status' => '1',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return Category::query()->findOrFail($id);
    }

    private function resolveDefaultFinishedGoodsStockId(?int $stockId): ?int
    {
        if ($stockId) {
            return $stockId;
        }

        $finishedGoodsStock = Stock::query()
            ->where('name', 'like', '%منتج تام%')
            ->orWhere('name', 'like', '%تام%')
            ->first();

        return $finishedGoodsStock?->id;
    }

    /**
     * اسم المخزن (warehouse) المطابق للمخزن المختار؛ افتراضياً «مخزن منتج تام»
     * حتى تظهر أصناف Shopify المُنشأة كأصناف منتج تام تماماً مثل بقية أصناف النظام.
     */
    private function resolveWarehouseNameForStock(?int $stockId): string
    {
        $default = 'مخزن منتج تام';
        if (! $stockId) {
            return $default;
        }
        $name = Stock::query()->where('id', $stockId)->value('name');

        return is_string($name) && trim($name) !== '' ? trim($name) : $default;
    }

    private function upsertShopifyMapping(
        int $variantId,
        int $categoryId,
        ?string $sku,
        ?string $shopDomain,
        ?int $shopifyProductId,
    ): void {
        $payload = [
            'category_id' => $categoryId,
            'sku' => $sku !== null && $sku !== '' ? $sku : null,
            'active' => true,
        ];
        if ($shopDomain) {
            $payload['shop_domain'] = $shopDomain;
        }
        if ($shopifyProductId) {
            $payload['shopify_product_id'] = $shopifyProductId;
        }

        ShopifyProductMapping::query()->updateOrCreate(
            ['shopify_variant_id' => $variantId],
            $payload
        );
    }

    /**
     * تحديث أسعار الأصناف في ERP بناءً على أسعار Shopify.
     *
     * Body:
     * - updates: array of { category_id, new_price }
     */
    public function updatePrices(Request $request)
    {
        $request->validate([
            'updates' => 'required|array|min:1',
            'updates.*.category_id' => 'required|integer|exists:categories,id',
            'updates.*.new_price' => 'required|numeric|min:0',
        ]);

        $updated = [];

        DB::transaction(function () use ($request, &$updated) {
            foreach ($request->input('updates') as $item) {
                $category = Category::find((int) $item['category_id']);
                if (! $category) {
                    continue;
                }
                $oldPrice = (float) ($category->sell_total_price ?? $category->category_price ?? 0);
                $newPrice = (float) $item['new_price'];

                $category->update([
                    'sell_total_price' => $newPrice,
                    'category_price' => $newPrice,
                ]);

                $updated[] = [
                    'category_id' => $category->id,
                    'category_name' => $category->category_name,
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                ];
            }
        });

        return response()->json([
            'success' => true,
            'message' => sprintf('تم تحديث أسعار %d صنف.', count($updated)),
            'updated' => $updated,
        ]);
    }
}
