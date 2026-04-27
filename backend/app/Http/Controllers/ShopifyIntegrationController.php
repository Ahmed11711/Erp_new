<?php

namespace App\Http\Controllers;

use App\Models\ShopifyProductMapping;
use App\Services\Shopify\ShopifyAdminApiClient;
use App\Services\Shopify\ShopifyOrdersSyncService;
use App\Services\Shopify\ShopifyProductMappingSyncService;
use Illuminate\Http\Request;

class ShopifyIntegrationController extends Controller
{
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
     */
    public function syncProductMappings(ShopifyProductMappingSyncService $syncService)
    {
        try {
            $result = $syncService->sync();

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
     * استيراد طلبات Shopify غير الموجودة محلياً (GET orders + نفس منطق الويب هوك).
     *
     * Body اختياري: days — عدد الأيام للخلف (افتراضي 30)، 0 = كل الطلبات (ترقيم كامل).
     */
    public function syncOrders(Request $request, ShopifyOrdersSyncService $syncService)
    {
        $days = $request->input('days', 30);
        $days = is_numeric($days) ? (int) $days : 30;
        if ($days < 0) {
            $days = 0;
        }
        if ($days > 3650) {
            $days = 3650;
        }

        try {
            $result = $syncService->sync($days);

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
}
