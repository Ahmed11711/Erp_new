<?php

namespace Tests\Unit;

use App\Models\AccountEntry;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\TreeAccount;
use App\Services\Accounting\SalesOrderLifecycleJournalService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SalesOrderLifecycleJournalServiceTest extends TestCase
{
    use DatabaseTransactions;

    private string $codePrefix = '';

    private ?TreeAccount $customerAcc = null;

    private ?TreeAccount $salesAcc = null;

    private ?TreeAccount $shippingRevAcc = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->codePrefix = 'Z'.substr(str_replace('.', '', uniqid('', true)), -10);
        $this->seedAccounts();
    }

    public function test_shipped_order_expects_shipping_stage_and_marks_invoice_posted(): void
    {
        $order = $this->makeOrder(1200, 0, 'تم شحن');
        OrderDetails::create([
            'order_id' => $order->id,
            'shipping_date' => '2026-09-01',
        ]);
        $this->postInvoice($order, 1200);

        $stages = app(SalesOrderLifecycleJournalService::class)->stagesForOrder($order->fresh('order_details'), [], [
            ['type' => 'invoice', 'title' => 'إثبات المبيعات — تاريخ الشحن'],
        ]);
        $byKey = collect($stages)->keyBy('key');

        $this->assertTrue($byKey['shipping_recognition']['applicable']);
        $this->assertTrue($byKey['shipping_recognition']['posted']);
        $this->assertFalse($byKey['shipping_recognition']['missing']);
        $this->assertFalse($byKey['cogs_recognition']['applicable']);
        $this->assertFalse($byKey['cogs_recognition']['missing']);
        $this->assertSame('2026-09-01', $byKey['shipping_recognition']['approved_date']);
        $this->assertFalse($byKey['advance_payment']['applicable']);
        $this->assertFalse($byKey['return_adjustment']['applicable']);
        $this->assertFalse($byKey['cogs_recognition']['anomaly']);
        $this->assertSame([], $byKey['cogs_recognition']['notes']);
    }

    public function test_duplicate_cogs_journals_are_flagged_as_accounting_duplication(): void
    {
        $order = $this->makeOrder(662, 0, 'رفض استلام');
        OrderDetails::create([
            'order_id' => $order->id,
            'shipping_date' => '2026-08-26',
            'canceled_date' => '2026-08-27',
            'postponed' => 3,
        ]);

        $journals = [
            ['type' => 'cogs', 'title' => 'تكلفة البضاعة المباعة', 'date' => '2026-08-08', 'total_debit' => 82.13, 'lines' => []],
            ['type' => 'cogs', 'title' => 'تكلفة البضاعة المباعة', 'date' => '2026-08-13', 'total_debit' => 82.13, 'lines' => []],
            ['type' => 'cogs', 'title' => 'تكلفة البضاعة المباعة', 'date' => '2026-08-24', 'total_debit' => 82.13, 'lines' => []],
            ['type' => 'cogs', 'title' => 'تكلفة البضاعة المباعة', 'date' => '2026-08-26', 'total_debit' => 82.13, 'lines' => []],
            ['type' => 'cogs_reversal', 'title' => 'عكس تكلفة البضاعة المباعة', 'date' => '2026-08-27', 'total_debit' => 82.13, 'lines' => []],
        ];

        $stages = app(SalesOrderLifecycleJournalService::class)->stagesForOrder(
            $order->fresh('order_details'),
            $journals
        );
        $cogs = collect($stages)->firstWhere('key', 'cogs_recognition');

        $this->assertTrue($cogs['anomaly']);
        $this->assertNotEmpty($cogs['notes']);
        $this->assertStringContainsString('تكرار غير صحيح', $cogs['notes'][1]['text']);
        $this->assertStringContainsString('246.39', $cogs['notes'][3]['text']);
        $this->assertStringContainsString('تكرار محاسبي', $cogs['journals'][1]['note']);
        $this->assertStringContainsString('القيد الأصلي', $cogs['journals'][0]['note']);
    }

    public function test_prepaid_and_refused_order_expects_advance_delivery_and_return(): void
    {
        $order = $this->makeOrder(1000, 200, 'رفض استلام');
        OrderDetails::create([
            'order_id' => $order->id,
            'shipping_date' => '2026-09-01',
            'canceled_date' => '2026-09-03',
            'remaining_amount' => 800,
        ]);

        $stages = app(SalesOrderLifecycleJournalService::class)->stagesForOrder($order->fresh('order_details'));
        $byKey = collect($stages)->keyBy('key');

        $this->assertTrue($byKey['advance_payment']['applicable']);
        $this->assertTrue($byKey['shipping_recognition']['applicable']);
        $this->assertTrue($byKey['delivery_transfer']['applicable']);
        $this->assertTrue($byKey['return_adjustment']['applicable']);
        $this->assertTrue($byKey['return_adjustment']['missing']);
        $this->assertSame('2026-09-03', $byKey['return_adjustment']['approved_date']);
        $this->assertEquals(800.0, $byKey['delivery_transfer']['amount']);
        $this->assertTrue($byKey['advance_payment']['missing']);
    }

    public function test_post_missing_skips_prepaid_when_already_present(): void
    {
        $order = $this->makeOrder(1000, 200, 'تم شحن');
        OrderDetails::create([
            'order_id' => $order->id,
            'shipping_date' => '2026-09-01',
        ]);
        AccountEntry::create([
            'tree_account_id' => $this->customerAcc->id,
            'debit' => 0,
            'credit' => 200,
            'description' => 'دفعة مقدمة — طلب رقم '.$order->id,
            'order_id' => $order->id,
            'entry_batch_code' => 'ORD-PREPAID-'.$order->id.'-20260901120000',
        ]);
        AccountEntry::create([
            'tree_account_id' => $this->salesAcc->id,
            'debit' => 200,
            'credit' => 0,
            'description' => 'دفعة مقدمة — طلب رقم '.$order->id,
            'order_id' => $order->id,
            'entry_batch_code' => 'ORD-PREPAID-'.$order->id.'-20260901120000',
        ]);

        $prepaidBefore = AccountEntry::query()
            ->where('order_id', $order->id)
            ->where('entry_batch_code', 'like', 'ORD-PREPAID-'.$order->id.'-%')
            ->count();
        $result = app(SalesOrderLifecycleJournalService::class)->postMissingForOrder($order->fresh('order_details'));

        $this->assertSame('skipped_exists', $result['prepaid']);
        $this->assertSame($prepaidBefore, AccountEntry::query()
            ->where('order_id', $order->id)
            ->where('entry_batch_code', 'like', 'ORD-PREPAID-'.$order->id.'-%')
            ->count());
    }

    public function test_invoice_is_deferred_until_shipping(): void
    {
        $order = $this->makeOrder(900, 0, 'طلب جديد');
        $service = app(\App\Services\Accounting\SalesOrderAccountingService::class);

        $this->assertFalse($service->isReadyForInvoiceRecognition($order));
        $this->assertSame('skipped_not_shipped', $service->recordInitialOrderRecognitionIfMissing($order));
        $this->assertFalse($service->hasInvoiceRecognition($order));

        $order->order_status = 'تم شحن';
        $order->save();
        $this->assertTrue($service->isReadyForInvoiceRecognition($order->fresh()));
    }

    public function test_posts_return_sales_adjustment_once(): void
    {
        $order = $this->makeOrder(1000, 0, 'رفض استلام');
        $service = app(SalesOrderLifecycleJournalService::class);

        $first = $service->postReturnSalesAdjustment($order);
        $this->assertSame('posted', $first);
        $this->assertTrue($service->hasReturnSalesAdjustment($order));

        $second = $service->postReturnSalesAdjustment($order);
        $this->assertSame('skipped_exists', $second);

        $debit = (float) AccountEntry::query()
            ->where('order_id', $order->id)
            ->where('entry_batch_code', 'like', 'RETURN-SALES-'.$order->id.'-%')
            ->sum('debit');
        $this->assertEquals(1000.0, $debit);
    }

    public function test_preview_and_post_delivery_when_invoice_exists_without_cogs(): void
    {
        $shipAr = TreeAccount::create([
            'code' => $this->codePrefix.'1122',
            'name' => 'ذمة مندوب دورة حياة',
            'type' => 'asset',
            'level' => 1,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);
        $company = \App\Models\ShippingCompany::create([
            'name' => 'مندوب دورة حياة '.$this->codePrefix,
            'type' => 'مندوب',
            'receivable_tree_account_id' => $shipAr->id,
        ]);
        $order = $this->makeOrder(5510, 0, 'تم التحصيل');
        OrderDetails::create([
            'order_id' => $order->id,
            'shipping_date' => '2026-06-03',
            'delivery_date' => '2026-06-04',
            'shipping_company_id' => $company->id,
            'remaining_amount' => 5510,
        ]);
        $this->postInvoice($order, 5510);

        $fresh = $order->fresh(['order_details.shipping_company']);
        $delivery = app(SalesOrderLifecycleJournalService::class)->previewDelivery($fresh);
        $this->assertTrue($delivery['applicable']);
        $this->assertTrue($delivery['can_post']);
        $this->assertCount(2, $delivery['lines']);
        $this->assertEquals(5510.0, $delivery['total']);

        $email = 'life_'.$this->codePrefix.'@test.local';
        config(['rbac.super_admin_emails' => [$email]]);
        $user = \App\Models\User::factory()->create([
            'email' => $email,
            'department' => 'LIFE-'.$this->codePrefix,
        ]);
        $preview = app(\App\Services\Accounting\MissingSalesInvoiceJournalService::class)
            ->previewOrder($user, (int) $order->id);
        $this->assertTrue($preview['can_post']);
        $this->assertNull($preview['reason']);
        $this->assertContains('delivery', array_column($preview['journals'], 'key'));
        $this->assertNotContains('cogs', array_column($preview['journals'], 'key'));

        $posted = app(SalesOrderLifecycleJournalService::class)->recordDeliveryWithLines(
            $fresh,
            $delivery['lines'],
            $delivery['description'],
            \Carbon\Carbon::parse('2026-06-04')->startOfDay()
        );
        $this->assertSame('posted', $posted);
        $this->assertEquals(5510.0, (float) AccountEntry::query()
            ->where('order_id', $order->id)
            ->where('entry_batch_code', 'like', 'DELIVERY-'.$order->id.'-%')
            ->sum('debit'));
    }

    private function seedAccounts(): void
    {
        $p = $this->codePrefix;
        $root = TreeAccount::create([
            'code' => $p.'1000',
            'name' => 'أصول دورة حياة',
            'type' => 'asset',
            'level' => 1,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);
        $this->customerAcc = TreeAccount::create([
            'code' => $p.'1101',
            'name' => 'عميل دورة حياة',
            'type' => 'asset',
            'level' => 2,
            'parent_id' => $root->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);
        $revenue = TreeAccount::create([
            'code' => $p.'4000',
            'name' => 'إيرادات دورة حياة',
            'type' => 'revenue',
            'level' => 1,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);
        $this->salesAcc = TreeAccount::create([
            'code' => $p.'4101',
            'name' => 'مبيعات دورة حياة',
            'type' => 'revenue',
            'level' => 2,
            'parent_id' => $revenue->id,
            'detail_type' => 'sales',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);
        $this->shippingRevAcc = TreeAccount::create([
            'code' => $p.'4301',
            'name' => 'شحن دورة حياة',
            'type' => 'revenue',
            'level' => 2,
            'parent_id' => $revenue->id,
            'detail_type' => 'shipping_revenue',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);
    }

    private function makeOrder(float $net, float $prepaid, string $status): Order
    {
        return Order::withoutEvents(function () use ($net, $prepaid, $status) {
            return Order::create([
                'customer_name' => 'عميل دورة حياة',
                'customer_type' => 'افراد',
                'customer_phone_1' => '018'.substr(str_replace('.', '', uniqid('', true)), -8),
                'customer_phone_2' => '',
                'governorate' => 'القاهرة',
                'city' => 'القاهرة',
                'address' => 'عنوان',
                'order_date' => '2026-08-20',
                'shipping_method_id' => 1,
                'order_source_id' => 1,
                'order_type' => 'جديد',
                'shipping_cost' => 0,
                'shipping_revenue' => 0,
                'total_invoice' => $net,
                'prepaid_amount' => $prepaid,
                'discount' => 0,
                'net_total' => $net,
                'order_status' => $status,
            ]);
        });
    }

    private function postInvoice(Order $order, float $amount): void
    {
        $batch = 'ORD-'.$order->id.'-20260901120000';
        AccountEntry::create([
            'tree_account_id' => $this->customerAcc->id,
            'debit' => $amount,
            'credit' => 0,
            'description' => 'فاتورة مبيعات — طلب رقم '.$order->id,
            'order_id' => $order->id,
            'entry_batch_code' => $batch,
        ]);
        AccountEntry::create([
            'tree_account_id' => $this->salesAcc->id,
            'debit' => 0,
            'credit' => $amount,
            'description' => 'فاتورة مبيعات — طلب رقم '.$order->id,
            'order_id' => $order->id,
            'entry_batch_code' => $batch,
        ]);
    }
}
