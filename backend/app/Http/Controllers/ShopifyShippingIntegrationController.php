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
        $perPage = min(max((int) $request->query('per_page', 25), 1), 1000);
        $q = trim((string) $request->query('q', ''));

        $query = Order::query()
            ->whereNotNull('shopify_order_id')
            ->with([
                'shipping_method:id,name',
                'order_products' => function ($builder) {
                    $builder->select([
                        'id',
                        'order_id',
                        'category_id',
                        'quantity',
                        'price',
                        'total_price',
                        'special_details',
                        'shopify_line_item_id',
                        'shopify_variant_id',
                        'shipped_quantity',
                        'cancelled_quantity',
                    ])->with(['category:id,category_name,item_code,category_image']);
                },
            ])
            ->orderByDesc('id');

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('reference_number', 'like', '%'.$q.'%')
                    ->orWhere('customer_name', 'like', '%'.$q.'%')
                    ->orWhere('customer_phone_1', 'like', '%'.$q.'%')
                    ->orWhere('shopify_order_id', 'like', '%'.$q.'%')
                    ->orWhere('id', 'like', '%'.$q.'%')
                    ->orWhere('shipping_tracking_number', 'like', '%'.$q.'%');
            });
        }

        return $query->paginate($perPage, [
            'id',
            'shopify_order_id',
            'shopify_shop_domain',
            'shopify_financial_status',
            'shopify_fulfillment_status',
            'shopify_payment_gateway',
            'shopify_needs_product_review',
            'shopify_reviewed_at',
            'shipping_tracking_number',
            'shipping_partner_status',
            'shipping_method_id',
            'customer_name',
            'customer_phone_1',
            'customer_phone_2',
            'governorate',
            'city',
            'address',
            'order_date',
            'shipping_cost',
            'total_invoice',
            'prepaid_amount',
            'discount',
            'vat',
            'net_total',
            'order_status',
            'reference_number',
            'collect_note',
            'order_notes',
            'created_at',
            'updated_at',
        ]);
    }

    public function integrationProducts(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 25), 1), 1000);
        $q = trim((string) $request->query('q', ''));

        $query = ShopifyProduct::query()
            ->with(['category:id,category_name,item_code,category_image'])
            ->orderByDesc('id');

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', '%'.$q.'%')
                    ->orWhere('sku', 'like', '%'.$q.'%')
                    ->orWhere('shopify_product_id', 'like', '%'.$q.'%')
                    ->orWhere('shopify_variant_id', 'like', '%'.$q.'%');
            });
        }

        return $query->paginate($perPage);
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
