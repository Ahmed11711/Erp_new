<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\OrderProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ConversationOrdersTest extends TestCase
{
    use DatabaseTransactions;

    public function test_messages_include_order_id_from_column_and_template_text(): void
    {
        [$user, $customer, $order] = $this->seedConversation();

        Message::create([
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'content' => 'Template: Feedback in Arabic - Order #'.$order->id,
            'type' => 'text',
            'direction' => 'outbound',
            'status' => 'sent',
        ]);

        Message::create([
            'customer_id' => $customer->id,
            'content' => 'متابعة طلب #'.$order->id.' بعد الشحن',
            'type' => 'text',
            'direction' => 'inbound',
            'status' => 'delivered',
        ]);

        $res = $this->actingAs($user)->getJson('/api/conversations/'.$customer->id.'/messages?limit=20');

        $res->assertOk()->assertJsonPath('success', true);
        $ids = collect($res->json('data'))->pluck('order_id')->filter()->unique()->values();
        $this->assertTrue($ids->contains($order->id));
    }

    public function test_conversation_orders_return_products_for_matching_phone(): void
    {
        [$user, $customer, $order, $productName] = $this->seedConversation();

        $res = $this->actingAs($user)->getJson('/api/conversations/'.$customer->id.'/orders');

        $res->assertOk()->assertJsonPath('success', true);
        $rows = collect($res->json('data'));
        $this->assertTrue($rows->contains(fn ($row) => (int) $row['id'] === (int) $order->id));

        $matched = $rows->firstWhere('id', $order->id);
        $this->assertNotEmpty($matched['products'] ?? []);
        $this->assertSame($productName, $matched['products'][0]['name']);
        $this->assertEquals(2, $matched['products'][0]['quantity']);
    }

    public function test_missing_conversation_returns_404(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->getJson('/api/conversations/999999991/orders')
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Customer, 2: Order, 3: string}
     */
    private function seedConversation(): array
    {
        $user = $this->makeUser();
        $suffix = substr(str_replace('.', '', uniqid('', true)), -7);
        $localPhone = '0109'.$suffix;
        $waPhone = '20109'.$suffix;

        $customer = Customer::create([
            'name' => 'عميل شات اختبار',
            'phone' => $waPhone,
        ]);

        $category = Category::query()->first();
        if (! $category) {
            $this->markTestSkipped('تحتاج جدولاً categories يحتوي صفاً واحداً على الأقل.');
        }

        $order = Order::withoutEvents(function () use ($localPhone) {
            $order = Order::create([
                'customer_name' => 'عميل شات اختبار',
                'customer_type' => 'فرد',
                'customer_phone_1' => $localPhone,
                'customer_phone_2' => '',
                'governorate' => 'القاهرة',
                'city' => 'القاهرة',
                'address' => 'عنوان',
                'order_date' => now()->toDateString(),
                'shipping_method_id' => 1,
                'order_source_id' => 1,
                'order_type' => 'جديد',
                'shipping_cost' => 0,
                'total_invoice' => 200,
                'prepaid_amount' => 0,
                'discount' => 0,
                'net_total' => 200,
                'order_status' => 'طلب جديد',
            ]);
            OrderDetails::create(['order_id' => $order->id]);

            return $order;
        });

        OrderProduct::create([
            'order_id' => $order->id,
            'category_id' => $category->id,
            'quantity' => 2,
            'price' => 100,
            'total_price' => 200,
        ]);

        return [$user, $customer, $order, (string) $category->category_name];
    }

    private function makeUser(): User
    {
        $user = User::factory()->create([
            'department' => 'Admin',
            'email' => 'wa_chat_'.uniqid().'@test.local',
        ]);
        config(['rbac.super_admin_emails' => [strtolower($user->email)]]);

        return $user;
    }
}
