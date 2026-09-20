<?php

namespace Tests\Unit;

use App\Models\AccountEntry;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\TreeAccount;
use App\Models\User;
use App\Services\Accounting\SalesMovementReportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SalesMovementReportServiceTest extends TestCase
{
    use DatabaseTransactions;

    private string $codePrefix = '';

    private ?TreeAccount $customerAcc = null;

    private ?TreeAccount $salesAcc = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->codePrefix = 'Z' . substr(str_replace('.', '', uniqid('', true)), -10);
        $this->seedAccounts();
    }

    public function test_order_date_mode_returns_only_orders_on_selected_day(): void
    {
        $user = $this->viewer();
        $onDay = $this->makeOrder('2099-03-14', 1415);
        $otherDay = $this->makeOrder('2099-03-15', 500);
        $this->postInvoice($onDay, 1415);
        $this->postInvoice($otherDay, 500);

        $report = app(SalesMovementReportService::class)->report(
            $user,
            '2099-03-14',
            '2099-03-14',
            SalesMovementReportService::MODE_ORDER_DATE,
            $onDay->customer_name
        );

        $ids = collect($report['data'])->pluck('order_id')->all();
        $this->assertContains($onDay->id, $ids);
        $this->assertNotContains($otherDay->id, $ids);
        $this->assertSame(1, $report['summary']['orders_count']);
        $this->assertEquals(1415.0, $report['summary']['invoice_net']);
        $this->assertSame(0, $report['summary']['missing_invoice_count']);
        $this->assertFalse($report['data'][0]['missing_invoice']);
        $this->assertTrue($report['data'][0]['has_invoice_journal']);
    }

    public function test_shipping_date_mode_uses_order_details_shipping_date(): void
    {
        $user = $this->viewer();
        $order = $this->makeOrder('2099-01-01', 200);
        OrderDetails::create([
            'order_id' => $order->id,
            'shipping_date' => '2099-03-14',
        ]);
        $this->postInvoice($order, 200);

        $report = app(SalesMovementReportService::class)->report(
            $user,
            '2099-03-14',
            '2099-03-14',
            SalesMovementReportService::MODE_SHIPPING_DATE,
            $order->customer_name
        );

        $this->assertSame([$order->id], collect($report['data'])->pluck('order_id')->all());
        $this->assertSame('2099-03-14', $report['data'][0]['movement_date']);
    }

    public function test_flags_orders_missing_invoice_journal(): void
    {
        $user = $this->viewer();
        $missing = $this->makeOrder('2099-04-01', 300, 'تم شحن');
        $posted = $this->makeOrder('2099-04-01', 400);
        $this->postInvoice($posted, 400);

        $report = app(SalesMovementReportService::class)->report(
            $user,
            '2099-04-01',
            '2099-04-01',
            SalesMovementReportService::MODE_ORDER_DATE,
            $this->codePrefix
        );

        $byId = collect($report['data'])->keyBy('order_id');
        $this->assertTrue((bool) $byId[$missing->id]['missing_invoice']);
        $this->assertFalse((bool) $byId[$posted->id]['missing_invoice']);
        $this->assertSame(1, $report['summary']['missing_invoice_count']);
        $this->assertSame($missing->id, $report['data'][0]['order_id']);
    }

    public function test_missing_only_filters_orders_without_invoice_journal(): void
    {
        $user = $this->viewer();
        $missing = $this->makeOrder('2099-04-02', 300, 'تم شحن');
        $posted = $this->makeOrder('2099-04-02', 400);
        $this->postInvoice($posted, 400);

        $report = app(SalesMovementReportService::class)->report(
            $user,
            '2099-04-02',
            '2099-04-02',
            SalesMovementReportService::MODE_ORDER_DATE,
            $this->codePrefix,
            1,
            15,
            true
        );

        $ids = collect($report['data'])->pluck('order_id')->all();
        $this->assertContains($missing->id, $ids);
        $this->assertNotContains($posted->id, $ids);
        $this->assertTrue((bool) $report['data'][0]['missing_invoice']);
    }

    public function test_summary_only_skips_rows_but_keeps_totals(): void
    {
        $user = $this->viewer();
        $this->makeOrder('2099-05-01', 250, 'تم شحن');
        $posted = $this->makeOrder('2099-05-01', 400);
        $this->postInvoice($posted, 400);

        $report = app(SalesMovementReportService::class)->report(
            $user,
            '2099-05-01',
            '2099-05-01',
            SalesMovementReportService::MODE_ORDER_DATE,
            $this->codePrefix,
            1,
            15,
            false,
            true
        );

        $this->assertSame([], $report['data']);
        $this->assertSame(2, $report['summary']['orders_count']);
        $this->assertSame(1, $report['summary']['missing_invoice_count']);
        $this->assertEquals(650.0, $report['summary']['invoice_net']);
        $this->assertSame(0.0, $report['summary']['journal_debit']);
    }

    public function test_journal_date_mode_uses_entry_created_at_when_unlinked(): void
    {
        $user = $this->viewer();
        $order = $this->makeOrder('2098-01-01', 180);
        $this->postInvoice($order, 180);

        $report = app(SalesMovementReportService::class)->report(
            $user,
            now()->toDateString(),
            now()->toDateString(),
            SalesMovementReportService::MODE_JOURNAL_DATE,
            $order->customer_name
        );

        $this->assertSame([$order->id], collect($report['data'])->pluck('order_id')->all());
    }

    private function viewer(): User
    {
        $email = 'smv_' . $this->codePrefix . '@test.local';
        config(['rbac.super_admin_emails' => [$email]]);

        return User::factory()->create([
            'email' => $email,
            'department' => 'SMV-' . $this->codePrefix,
        ]);
    }

    private function seedAccounts(): void
    {
        $p = $this->codePrefix;
        $this->customerAcc = TreeAccount::create([
            'code' => $p . '1101',
            'name' => 'عميل حركة مبيعات',
            'type' => 'asset',
            'level' => 1,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);
        $this->salesAcc = TreeAccount::create([
            'code' => $p . '4101',
            'name' => 'إيراد حركة مبيعات',
            'type' => 'revenue',
            'level' => 1,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);
    }

    private function makeOrder(string $orderDate, float $netTotal, string $status = 'طلب جديد'): Order
    {
        return Order::withoutEvents(function () use ($orderDate, $netTotal, $status) {
            return Order::create([
                'customer_name' => 'عميل حركة فريد ' . $this->codePrefix,
                'customer_type' => 'افراد',
                'customer_phone_1' => '018' . substr(str_replace('.', '', uniqid('', true)), -8),
                'customer_phone_2' => '',
                'governorate' => 'القاهرة',
                'city' => 'القاهرة',
                'address' => 'عنوان',
                'order_date' => $orderDate,
                'shipping_method_id' => 1,
                'order_source_id' => 1,
                'order_type' => 'جديد',
                'shipping_cost' => 0,
                'total_invoice' => $netTotal,
                'prepaid_amount' => 0,
                'discount' => 0,
                'net_total' => $netTotal,
                'order_status' => $status,
            ]);
        });
    }

    private function postInvoice(Order $order, float $amount): void
    {
        $batch = 'ORD-' . $order->id . '-20250505120000';
        AccountEntry::create([
            'tree_account_id' => $this->customerAcc->id,
            'debit' => $amount,
            'credit' => 0,
            'description' => 'فاتورة مبيعات — طلب رقم ' . $order->id,
            'order_id' => $order->id,
            'entry_batch_code' => $batch,
        ]);
        AccountEntry::create([
            'tree_account_id' => $this->salesAcc->id,
            'debit' => 0,
            'credit' => $amount,
            'description' => 'فاتورة مبيعات — طلب رقم ' . $order->id,
            'order_id' => $order->id,
            'entry_batch_code' => $batch,
        ]);
    }
}
