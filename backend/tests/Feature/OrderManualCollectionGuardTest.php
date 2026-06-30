<?php

namespace Tests\Feature;

use App\Enums\CollectionProviderType;
use App\Models\Bank;
use App\Models\CollectionCompany;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\shippingCompanyDetails;
use App\Models\User;
use App\Services\Orders\OrderManualCollectionGuard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OrderManualCollectionGuardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_blocks_manual_collect_when_only_collection_receivable_is_open(): void
    {
        $order = Order::withoutEvents(function () {
            $order = Order::create([
                'customer_name' => 'عميل تحصيل',
                'customer_type' => 'فرد',
                'customer_phone_1' => '01099998888',
                'customer_phone_2' => '',
                'governorate' => 'القاهرة',
                'city' => 'القاهرة',
                'address' => 'عنوان',
                'order_date' => now()->toDateString(),
                'shipping_method_id' => 1,
                'order_source_id' => 1,
                'order_type' => 'جديد',
                'shipping_cost' => 0,
                'total_invoice' => 500,
                'prepaid_amount' => 500,
                'discount' => 0,
                'net_total' => 0,
                'order_status' => 'تم التسليم',
            ]);

            OrderDetails::create([
                'order_id' => $order->id,
                'collection_provider_type' => CollectionProviderType::CollectionCompany->value,
                'collection_provider_id' => 1,
                'shipping_receivable_amount' => 0,
                'collection_receivable_amount' => 500,
            ]);

            return $order;
        });

        $guard = app(OrderManualCollectionGuard::class);

        $this->assertFalse($guard->allowsManualShippingCollection($order));
        $this->assertTrue($guard->allowsManualOrderCollection($order));
        $this->assertSame(500.0, $guard->openCollectionReceivableAmount($order));
        $this->assertFalse($guard->shouldMarkOrderFullyCollected($order));
    }

    public function test_allows_manual_collect_for_cod_remainder_with_collection_prepaid(): void
    {
        $order = Order::withoutEvents(function () {
            $order = Order::create([
                'customer_name' => 'عميل جزئي',
                'customer_type' => 'فرد',
                'customer_phone_1' => '01099997777',
                'customer_phone_2' => '',
                'governorate' => 'القاهرة',
                'city' => 'القاهرة',
                'address' => 'عنوان',
                'order_date' => now()->toDateString(),
                'shipping_method_id' => 1,
                'order_source_id' => 1,
                'order_type' => 'جديد',
                'shipping_cost' => 0,
                'total_invoice' => 2000,
                'prepaid_amount' => 500,
                'discount' => 0,
                'net_total' => 1500,
                'order_status' => 'تم التسليم',
            ]);

            OrderDetails::create([
                'order_id' => $order->id,
                'collection_provider_type' => CollectionProviderType::CollectionCompany->value,
                'collection_provider_id' => 1,
                'shipping_receivable_amount' => 1500,
                'collection_receivable_amount' => 500,
            ]);

            return $order;
        });

        $guard = app(OrderManualCollectionGuard::class);

        $this->assertTrue($guard->allowsManualShippingCollection($order));
        $this->assertSame(1500.0, $guard->shippingCodAmount($order));
        $this->assertFalse($guard->shouldMarkOrderFullyCollected($order));
    }

    public function test_collect_order_api_succeeds_for_collection_only_prepaid_order(): void
    {
        $user = User::factory()->create(['department' => 'Admin']);
        $this->actingAs($user, 'api');

        $bank = Bank::query()->whereNotNull('asset_id')->first();
        if (! $bank) {
            $this->markTestSkipped('Need a bank linked to tree account.');
        }

        $collectionCompany = CollectionCompany::query()->first();
        if (! $collectionCompany) {
            $collectionCompany = CollectionCompany::create([
                'name' => 'Paymob Test ' . uniqid(),
                'status' => 'active',
            ]);
        }

        $order = Order::withoutEvents(function () use ($collectionCompany) {
            $order = Order::create([
                'customer_name' => 'عميل API',
                'customer_type' => 'فرد',
                'customer_phone_1' => '01099996666',
                'customer_phone_2' => '',
                'governorate' => 'القاهرة',
                'city' => 'القاهرة',
                'address' => 'عنوان',
                'order_date' => now()->toDateString(),
                'shipping_method_id' => 1,
                'order_source_id' => 1,
                'order_type' => 'جديد',
                'shipping_cost' => 0,
                'total_invoice' => 800,
                'prepaid_amount' => 800,
                'discount' => 0,
                'net_total' => 0,
                'order_status' => 'تم التسليم',
            ]);

            OrderDetails::create([
                'order_id' => $order->id,
                'collection_provider_type' => CollectionProviderType::CollectionCompany->value,
                'collection_provider_id' => $collectionCompany->id,
                'shipping_receivable_amount' => 0,
                'collection_receivable_amount' => 800,
            ]);

            return $order;
        });

        $response = $this->postJson('/api/collectorder/' . $order->id, [
            'payment_type' => 'bank',
            'bank_id' => $bank->id,
        ]);

        $response->assertOk();
        $order->refresh();
        $this->assertSame('تم التحصيل', $order->order_status);
        $this->assertEqualsWithDelta(0.0, (float) $order->order_details->collection_receivable_amount, 0.02);
    }

    public function test_collect_order_resolves_collection_company_from_shopify_gateway(): void
    {
        $user = User::factory()->create(['department' => 'Admin']);
        $this->actingAs($user, 'api');

        $bank = Bank::query()->whereNotNull('asset_id')->first();
        if (! $bank) {
            $this->markTestSkipped('Need a bank linked to tree account.');
        }

        $visa = CollectionCompany::query()->whereRaw('LOWER(name) = ?', ['visa'])->first()
            ?? CollectionCompany::create(['name' => 'Visa', 'status' => 'active']);

        $order = Order::withoutEvents(function () use ($visa) {
            $order = Order::create([
                'customer_name' => 'Gateway Test',
                'customer_type' => 'فرد',
                'customer_phone_1' => '01088887777',
                'customer_phone_2' => '',
                'governorate' => 'القاهرة',
                'city' => 'القاهرة',
                'address' => 'عنوان',
                'order_date' => now()->toDateString(),
                'shipping_method_id' => 1,
                'order_source_id' => 1,
                'order_type' => 'جديد',
                'shipping_cost' => 0,
                'total_invoice' => 600,
                'prepaid_amount' => 600,
                'discount' => 0,
                'net_total' => 0,
                'order_status' => 'تم التسليم',
                'shopify_payment_gateway' => 'Paymob - Native Checkout (Shopify)',
            ]);

            OrderDetails::create([
                'order_id' => $order->id,
                'shipping_receivable_amount' => 0,
                'collection_receivable_amount' => 600,
                'collection_provider_type' => null,
                'collection_provider_id' => null,
            ]);

            return $order;
        });

        $this->postJson('/api/collectorder/' . $order->id, [
            'payment_type' => 'bank',
            'bank_id' => $bank->id,
        ])->assertOk();

        $order->refresh();
        $this->assertSame('تم التحصيل', $order->order_status);
        $this->assertSame(CollectionProviderType::CollectionCompany->value, $order->order_details->collection_provider_type);
        $this->assertNotNull($order->order_details->collection_provider_id);
        $this->assertNotNull(CollectionCompany::find((int) $order->order_details->collection_provider_id));
    }

    public function test_courier_row_included_when_collection_legacy_id_equals_courier(): void
    {
        $guard = app(OrderManualCollectionGuard::class);
        $od = new OrderDetails([
            'shipping_company_id' => 10,
            'collection_company_id' => 10,
            'shipping_receivable_amount' => 2345,
        ]);

        $this->assertFalse($guard->isCollectionCompanyShippingRow(10, $od));
    }

    public function test_collection_row_excluded_when_on_separate_company(): void
    {
        $guard = app(OrderManualCollectionGuard::class);
        $od = new OrderDetails([
            'shipping_company_id' => 10,
            'collection_company_id' => 20,
            'shipping_receivable_amount' => 1500,
            'collection_receivable_amount' => 500,
        ]);

        $this->assertTrue($guard->isCollectionCompanyShippingRow(20, $od));
        $this->assertFalse($guard->isCollectionCompanyShippingRow(10, $od));
    }

    public function test_manual_collection_allowed_when_only_collection_shipping_rows_open(): void
    {
        $collectionCo = CollectionCompany::query()->first()
            ?? CollectionCompany::create(['name' => 'CollCo Test', 'status' => 'active', 'linked_shipping_company_id' => 20]);

        if (! $collectionCo->linked_shipping_company_id) {
            $collectionCo->linked_shipping_company_id = 20;
            $collectionCo->save();
        }

        $order = Order::withoutEvents(function () use ($collectionCo) {
            $order = Order::create([
                'customer_name' => 'عميل',
                'customer_type' => 'فرد',
                'customer_phone_1' => '01011112222',
                'customer_phone_2' => '',
                'governorate' => 'القاهرة',
                'city' => 'القاهرة',
                'address' => 'عنوان',
                'order_date' => now()->toDateString(),
                'shipping_method_id' => 1,
                'order_source_id' => 1,
                'order_type' => 'جديد',
                'shipping_cost' => 0,
                'total_invoice' => 2000,
                'prepaid_amount' => 500,
                'discount' => 0,
                'net_total' => 1500,
                'order_status' => 'تم التسليم',
            ]);

            OrderDetails::create([
                'order_id' => $order->id,
                'shipping_company_id' => 10,
                'collection_company_id' => 20,
                'collection_provider_type' => CollectionProviderType::CollectionCompany->value,
                'collection_provider_id' => $collectionCo->id,
                'shipping_receivable_amount' => 1500,
                'collection_receivable_amount' => 500,
            ]);

            \App\Models\shippingCompanyDetails::create([
                'order_id' => $order->id,
                'shipping_company_id' => 20,
                'shipping_date' => now()->toDateString(),
                'status' => 'تم التسليم',
                'amount' => 500,
                'is_done' => 0,
                'by' => 'test',
            ]);

            return $order;
        });

        $guard = app(OrderManualCollectionGuard::class);

        $this->assertTrue($guard->allowsManualOrderCollection($order));
        $this->assertNull($guard->manualCollectionUnavailableMessage($order));
    }

    public function test_manual_collection_allowed_when_open_row_on_courier_matching_collection_legacy(): void
    {
        $order = Order::withoutEvents(function () {
            $order = Order::create([
                'customer_name' => 'Heba',
                'customer_type' => 'فرد',
                'customer_phone_1' => '01277844708',
                'customer_phone_2' => '',
                'governorate' => 'القاهرة',
                'city' => 'القاهرة',
                'address' => 'عنوان',
                'order_date' => now()->toDateString(),
                'shipping_method_id' => 1,
                'order_source_id' => 1,
                'order_type' => 'جديد',
                'shipping_cost' => 95,
                'total_invoice' => 2345,
                'prepaid_amount' => 0,
                'discount' => 0,
                'net_total' => 2345,
                'order_status' => 'تم التسليم',
            ]);

            OrderDetails::create([
                'order_id' => $order->id,
                'shipping_company_id' => 10,
                'collection_company_id' => 10,
            ]);

            \App\Models\shippingCompanyDetails::create([
                'order_id' => $order->id,
                'shipping_company_id' => 10,
                'shipping_date' => now()->toDateString(),
                'status' => 'تم التسليم',
                'amount' => 2345,
                'is_done' => 0,
                'by' => 'test',
            ]);

            return $order;
        });

        $guard = app(OrderManualCollectionGuard::class);

        $this->assertNull($guard->manualCollectionUnavailableMessage($order));
        $this->assertCount(1, $guard->openManualCollectShippingRows($order));
    }
}
