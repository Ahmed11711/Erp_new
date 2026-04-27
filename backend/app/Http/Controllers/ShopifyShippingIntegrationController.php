<?php

namespace App\Http\Controllers;

use App\Jobs\UpdateProductToShopifyJob;
use App\Models\Order;
use App\Models\ShopifyProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopifyShippingIntegrationController extends Controller
{
    public function integrationOrders(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);

        return Order::query()
            ->whereNotNull('shopify_order_id')
            ->orderByDesc('id')
            ->paginate($perPage, [
                'id',
                'shopify_order_id',
                'shopify_financial_status',
                'shopify_fulfillment_status',
                'shipping_tracking_number',
                'shipping_partner_status',
                'customer_name',
                'customer_phone_1',
                'net_total',
                'order_status',
                'reference_number',
                'updated_at',
            ]);
    }

    public function integrationProducts(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);

        return ShopifyProduct::query()
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function updateIntegrationProduct(Request $request, int $id)
    {
        $shopify_product = ShopifyProduct::query()->findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:500'],
            'sku' => ['nullable', 'string', 'max:191'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'quantity' => ['sometimes', 'integer', 'min:0'],
        ]);

        $shopify_product->fill($data);
        $shopify_product->save();

        UpdateProductToShopifyJob::dispatch($shopify_product->id);

        return response()->json([
            'message' => 'Product saved; Shopify sync queued.',
            'product' => $shopify_product->fresh(),
        ]);
    }

    public function failedJobs(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        return DB::table('failed_jobs')
            ->selectRaw('id, uuid, queue, failed_at, SUBSTRING(exception, 1, 600) as exception_preview')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
