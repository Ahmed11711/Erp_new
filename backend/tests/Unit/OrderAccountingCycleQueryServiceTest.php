<?php

namespace Tests\Unit;

use App\Models\AccountEntry;
use App\Models\Order;
use App\Models\TreeAccount;
use App\Services\Accounting\OrderAccountingCycleQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OrderAccountingCycleQueryServiceTest extends TestCase
{
    use DatabaseTransactions;

    private string $codePrefix = '';

    private ?TreeAccount $customerAcc = null;

    private ?TreeAccount $salesAcc = null;

    private ?TreeAccount $cogsAcc = null;

    private ?TreeAccount $inventoryAcc = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->codePrefix = 'Z' . substr(str_replace('.', '', uniqid('', true)), -10);
        $this->seedAccounts();
    }

    public function test_groups_invoice_courier_and_unlinked_cogs_without_leaking_neighbor_order(): void
    {
        $order = $this->makeOrder();
        $neighbor = $this->makeOrder();

        $invoiceBatch = 'ORD-' . $order->id . '-20260829120000';
        $this->postPair(
            $this->customerAcc->id,
            $this->salesAcc->id,
            1200,
            'فاتورة مبيعات — طلب رقم ' . $order->id,
            $order->id,
            $invoiceBatch
        );

        $courierBatch = 'SHIP-COST-' . $order->id . '-20260829120100';
        $this->postPair(
            $this->cogsAcc->id,
            $this->customerAcc->id,
            150,
            'شحن صادر — طلب رقم ' . $order->id . ' — مصروف توصيل',
            $order->id,
            $courierBatch
        );

        AccountEntry::create([
            'tree_account_id' => $this->cogsAcc->id,
            'debit' => 400,
            'credit' => 0,
            'description' => 'تكلفة البضاعة المباعة للطلب رقم ' . $order->id,
            'order_id' => null,
            'entry_batch_code' => null,
        ]);
        AccountEntry::create([
            'tree_account_id' => $this->inventoryAcc->id,
            'debit' => 0,
            'credit' => 400,
            'description' => 'تكلفة البضاعة المباعة للطلب رقم ' . $order->id,
            'order_id' => null,
            'entry_batch_code' => null,
        ]);

        $neighborBatch = 'ORD-' . $neighbor->id . '-20260829120000';
        $this->postPair(
            $this->customerAcc->id,
            $this->salesAcc->id,
            99,
            'فاتورة مبيعات — طلب رقم ' . $neighbor->id,
            $neighbor->id,
            $neighborBatch
        );
        AccountEntry::create([
            'tree_account_id' => $this->cogsAcc->id,
            'debit' => 10,
            'credit' => 0,
            'description' => 'تكلفة البضاعة المباعة للطلب رقم ' . $neighbor->id,
            'order_id' => null,
        ]);

        AccountEntry::create([
            'tree_account_id' => $this->inventoryAcc->id,
            'debit' => 50,
            'credit' => 0,
            'description' => 'رفض استلام — إرجاع تكلفة للمخزون — طلب ' . $order->id,
            'order_id' => null,
            'entry_batch_code' => null,
        ]);
        AccountEntry::create([
            'tree_account_id' => $this->cogsAcc->id,
            'debit' => 0,
            'credit' => 50,
            'description' => 'رفض استلام — إرجاع تكلفة للمخزون — طلب ' . $order->id,
            'order_id' => null,
            'entry_batch_code' => null,
        ]);

        $cycle = app(OrderAccountingCycleQueryService::class)->forOrder($order);

        $this->assertSame($order->id, $cycle['order_id']);
        $this->assertSame(4, $cycle['journals_count']);
        $this->assertEquals(1800.0, $cycle['total_debit']);
        $this->assertEquals(1800.0, $cycle['total_credit']);

        $types = array_column($cycle['journals'], 'type');
        $this->assertContains('invoice', $types);
        $this->assertContains('courier_cost', $types);
        $this->assertContains('cogs', $types);
        $this->assertContains('cogs_reversal', $types);
        $this->assertArrayHasKey('stages', $cycle);
        $stageKeys = array_column($cycle['stages'], 'key');
        $this->assertContains('shipping_recognition', $stageKeys);
        $this->assertContains('advance_payment', $stageKeys);

        $allDescs = collect($cycle['journals'])
            ->flatMap(fn ($j) => $j['lines'])
            ->pluck('description')
            ->implode(' ');
        $this->assertStringNotContainsString('طلب رقم ' . $neighbor->id, $allDescs);
        $this->assertStringNotContainsString('99', collect($cycle['journals'])->pluck('total_debit')->implode(','));
    }

    public function test_for_many_and_summaries_use_linked_entries_without_leaking_neighbor(): void
    {
        $order = $this->makeOrder();
        $neighbor = $this->makeOrder();
        $invoiceBatch = 'ORD-' . $order->id . '-20260830120000';
        $this->postPair(
            $this->customerAcc->id,
            $this->salesAcc->id,
            500,
            'فاتورة مبيعات — طلب رقم ' . $order->id,
            $order->id,
            $invoiceBatch
        );
        $this->postPair(
            $this->customerAcc->id,
            $this->salesAcc->id,
            80,
            'فاتورة مبيعات — طلب رقم ' . $neighbor->id,
            $neighbor->id,
            'ORD-' . $neighbor->id . '-20260830120000'
        );

        $many = app(OrderAccountingCycleQueryService::class)->forMany([$order, $neighbor]);
        $this->assertSame(500.0, $many[$order->id]['total_debit']);
        $this->assertSame(80.0, $many[$neighbor->id]['total_debit']);

        $summaries = app(OrderAccountingCycleQueryService::class)->summariesForOrderIds([$order->id, $neighbor->id]);
        $this->assertTrue($summaries[$order->id]['has_invoice']);
        $this->assertTrue($summaries[$neighbor->id]['has_invoice']);
        $this->assertSame(500.0, $summaries[$order->id]['total_debit']);
        $this->assertSame(80.0, $summaries[$neighbor->id]['total_debit']);
    }

    private function seedAccounts(): void
    {
        $p = $this->codePrefix;
        $root = TreeAccount::create([
            'code' => $p . '1000',
            'name' => 'أصول اختبار دورة',
            'type' => 'asset',
            'level' => 1,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->customerAcc = TreeAccount::create([
            'code' => $p . '1101',
            'name' => 'عميل اختبار دورة',
            'type' => 'asset',
            'level' => 2,
            'parent_id' => $root->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);
        $this->inventoryAcc = TreeAccount::create([
            'code' => $p . '1201',
            'name' => 'مخزون اختبار دورة',
            'type' => 'asset',
            'level' => 2,
            'parent_id' => $root->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);
        $this->salesAcc = TreeAccount::create([
            'code' => $p . '4101',
            'name' => 'إيراد اختبار دورة',
            'type' => 'revenue',
            'level' => 1,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);
        $this->cogsAcc = TreeAccount::create([
            'code' => $p . '5101',
            'name' => 'تكلفة اختبار دورة',
            'type' => 'expense',
            'level' => 1,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);
    }

    private function makeOrder(): Order
    {
        return Order::withoutEvents(function () {
            return Order::create([
                'customer_name' => 'عميل دورة محاسبية',
                'customer_type' => 'افراد',
                'customer_phone_1' => '019' . substr(str_replace('.', '', uniqid('', true)), -8),
                'customer_phone_2' => '',
                'governorate' => 'القاهرة',
                'city' => 'القاهرة',
                'address' => 'عنوان',
                'order_date' => now()->toDateString(),
                'shipping_method_id' => 1,
                'order_source_id' => 1,
                'order_type' => 'جديد',
                'shipping_cost' => 0,
                'total_invoice' => 1200,
                'prepaid_amount' => 0,
                'discount' => 0,
                'net_total' => 1200,
                'order_status' => 'طلب جديد',
            ]);
        });
    }

    private function postPair(
        int $debitAccountId,
        int $creditAccountId,
        float $amount,
        string $description,
        int $orderId,
        string $batchCode
    ): void {
        AccountEntry::create([
            'tree_account_id' => $debitAccountId,
            'debit' => $amount,
            'credit' => 0,
            'description' => $description,
            'order_id' => $orderId,
            'entry_batch_code' => $batchCode,
        ]);
        AccountEntry::create([
            'tree_account_id' => $creditAccountId,
            'debit' => 0,
            'credit' => $amount,
            'description' => $description,
            'order_id' => $orderId,
            'entry_batch_code' => $batchCode,
        ]);
    }
}
