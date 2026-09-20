<?php

namespace Tests\Unit;

use App\Models\AccountEntry;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\TreeAccount;
use App\Models\User;
use App\Services\Accounting\MissingSalesInvoiceJournalService;
use App\Services\Accounting\SalesOrderAccountingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MissingSalesInvoiceJournalServiceTest extends TestCase
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

    public function test_preview_counts_only_orders_without_invoice_batch(): void
    {
        $user = $this->viewer();
        $missing = $this->makeOrder('2099-05-05', 1415, 'تم شحن');
        $posted = $this->makeOrder('2099-05-05', 200);
        $cancelled = $this->makeOrder('2099-05-05', 90, 'ملغي');
        $offer = $this->makeOrder('2099-05-05', 80);
        $offer->offer_debt_posted = true;
        $offer->save();
        $this->postInvoice($posted, 200);

        $preview = app(MissingSalesInvoiceJournalService::class)->preview(
            $user,
            '2099-05-05',
            '2099-05-05'
        );

        $this->assertSame(1, $preview['missing_count']);
        $this->assertSame(1, $preview['missing_invoice_count']);
        $this->assertContains($missing->id, $preview['sample_order_ids']);
        $this->assertNotContains($posted->id, $preview['sample_order_ids']);
        $this->assertNotContains($cancelled->id, $preview['sample_order_ids']);
        $this->assertNotContains($offer->id, $preview['sample_order_ids']);
        $this->assertGreaterThanOrEqual(1, $preview['skipped_ineligible_count']);
    }

    public function test_preview_by_shipping_date_ignores_order_date(): void
    {
        $user = $this->viewer();
        $shippedInRange = $this->makeOrder('2036-01-01', 300, 'تم شحن');
        OrderDetails::create([
            'order_id' => $shippedInRange->id,
            'shipping_date' => '2037-08-19',
        ]);
        $otherShipDate = $this->makeOrder('2037-08-19', 220, 'تم شحن');
        OrderDetails::create([
            'order_id' => $otherShipDate->id,
            'shipping_date' => '2036-01-01',
        ]);

        $byShip = app(MissingSalesInvoiceJournalService::class)->preview(
            $user,
            '2037-08-19',
            '2037-08-19',
            MissingSalesInvoiceJournalService::MODE_SHIPPING_DATE
        );
        $byOrder = app(MissingSalesInvoiceJournalService::class)->preview(
            $user,
            '2037-08-19',
            '2037-08-19',
            MissingSalesInvoiceJournalService::MODE_ORDER_DATE
        );

        $this->assertSame(MissingSalesInvoiceJournalService::MODE_SHIPPING_DATE, $byShip['mode']);
        $this->assertContains($shippedInRange->id, $byShip['sample_order_ids']);
        $this->assertNotContains($otherShipDate->id, $byShip['sample_order_ids']);
        $this->assertContains($otherShipDate->id, $byOrder['sample_order_ids']);
        $this->assertNotContains($shippedInRange->id, $byOrder['sample_order_ids']);
    }

    public function test_if_missing_skips_when_invoice_already_exists(): void
    {
        $order = $this->makeOrder('2099-05-05', 500);
        $this->postInvoice($order, 500);
        $before = AccountEntry::query()->where('order_id', $order->id)->count();

        $result = app(SalesOrderAccountingService::class)->recordInitialOrderRecognitionIfMissing($order);

        $this->assertSame('skipped_exists', $result);
        $this->assertSame($before, AccountEntry::query()->where('order_id', $order->id)->count());
    }

    public function test_if_missing_skips_cancelled_and_offer_converted(): void
    {
        $service = app(SalesOrderAccountingService::class);

        $cancelled = $this->makeOrder('2099-05-05', 100, 'ملغي');
        $this->assertSame('skipped_ineligible', $service->recordInitialOrderRecognitionIfMissing($cancelled));
        $this->assertSame(0, AccountEntry::query()->where('order_id', $cancelled->id)->count());

        $offer = $this->makeOrder('2099-05-05', 100);
        $offer->offer_debt_posted = true;
        $offer->save();
        $this->assertSame('skipped_ineligible', $service->recordInitialOrderRecognitionIfMissing($offer));
        $this->assertSame(0, AccountEntry::query()->where('order_id', $offer->id)->count());
    }

    public function test_post_missing_does_not_delete_existing_invoice(): void
    {
        $user = $this->viewer();
        $posted = $this->makeOrder('2099-05-06', 777);
        $this->postInvoice($posted, 777);
        $before = AccountEntry::query()
            ->where('order_id', $posted->id)
            ->where('entry_batch_code', 'like', 'ORD-' . $posted->id . '-%')
            ->count();

        $result = app(MissingSalesInvoiceJournalService::class)->postMissing(
            $user,
            '2099-05-06',
            '2099-05-06'
        );

        $this->assertTrue($result['success']);
        $this->assertSame($before, AccountEntry::query()
            ->where('order_id', $posted->id)
            ->where('entry_batch_code', 'like', 'ORD-' . $posted->id . '-%')
            ->count());
    }

    public function test_preview_order_rejects_cancelled_and_existing_invoice(): void
    {
        $user = $this->viewer();
        $cancelled = $this->makeOrder('2099-05-07', 100, 'ملغي');
        $posted = $this->makeOrder('2099-05-07', 250);
        $this->postInvoice($posted, 250);

        $cancelledPreview = app(MissingSalesInvoiceJournalService::class)->previewOrder($user, (int) $cancelled->id);
        $this->assertFalse($cancelledPreview['can_post']);
        $this->assertNotEmpty($cancelledPreview['reason']);
        $this->assertArrayHasKey('cogs', $cancelledPreview);
        $this->assertArrayHasKey('journals', $cancelledPreview);
        $this->assertContains('invoice', array_column($cancelledPreview['journals'], 'key'));
        $this->assertNotContains('prepaid', array_column($cancelledPreview['journals'], 'key'));

        $postedPreview = app(MissingSalesInvoiceJournalService::class)->previewOrder($user, (int) $posted->id);
        $this->assertFalse($postedPreview['can_post']);
        $this->assertSame('قيد إثبات الفاتورة موجود مسبقاً.', $postedPreview['reason']);
        $this->assertNotContains('prepaid', array_column($postedPreview['journals'], 'key'));
    }

    public function test_preview_order_includes_prepaid_only_when_applicable(): void
    {
        $user = $this->viewer();
        $without = $this->makeOrder('2099-05-12', 100, 'تم شحن');
        $withPrepaid = $this->makeOrder('2099-05-12', 100, 'تم شحن');
        $withPrepaid->prepaid_amount = 50;
        $withPrepaid->save();

        $withoutPreview = app(MissingSalesInvoiceJournalService::class)->previewOrder($user, (int) $without->id);
        $withPreview = app(MissingSalesInvoiceJournalService::class)->previewOrder($user, (int) $withPrepaid->id);

        $this->assertNotContains('prepaid', array_column($withoutPreview['journals'], 'key'));
        $this->assertContains('prepaid', array_column($withPreview['journals'], 'key'));
    }

    public function test_post_order_with_custom_lines_creates_invoice_batch(): void
    {
        $user = $this->viewer();
        $order = $this->makeOrder('2099-05-08', 12401);

        $result = app(MissingSalesInvoiceJournalService::class)->postOrder(
            $user,
            (int) $order->id,
            [
                [
                    'account_id' => (int) $this->customerAcc->id,
                    'debit' => 12401,
                    'credit' => 0,
                    'description' => 'ذمم العميل — إجمالي الفاتورة',
                ],
                [
                    'account_id' => (int) $this->salesAcc->id,
                    'debit' => 0,
                    'credit' => 12401,
                    'description' => 'إيرادات المبيعات (بضاعة)',
                ],
            ],
            '2099-05-08',
            'فاتورة مبيعات — طلب رقم '.$order->id
        );

        $this->assertTrue($result['success']);
        $this->assertSame('posted', $result['status']);
        $this->assertTrue(app(SalesOrderAccountingService::class)->hasInvoiceRecognition($order->fresh()));
        $this->assertEquals(12401.0, (float) AccountEntry::query()->where('order_id', $order->id)->sum('debit'));
    }

    public function test_post_order_rejects_unbalanced_lines(): void
    {
        $user = $this->viewer();
        $order = $this->makeOrder('2099-05-09', 100);

        $this->expectException(\InvalidArgumentException::class);
        app(MissingSalesInvoiceJournalService::class)->postOrder(
            $user,
            (int) $order->id,
            [
                ['account_id' => (int) $this->customerAcc->id, 'debit' => 100, 'credit' => 0],
                ['account_id' => (int) $this->salesAcc->id, 'debit' => 0, 'credit' => 80],
            ]
        );
    }

    public function test_post_order_does_not_replace_existing_invoice(): void
    {
        $user = $this->viewer();
        $order = $this->makeOrder('2099-05-10', 300);
        $this->postInvoice($order, 300);
        $before = AccountEntry::query()->where('order_id', $order->id)->count();

        $result = app(MissingSalesInvoiceJournalService::class)->postOrder(
            $user,
            (int) $order->id,
            [
                ['account_id' => (int) $this->customerAcc->id, 'debit' => 50, 'credit' => 0],
                ['account_id' => (int) $this->salesAcc->id, 'debit' => 0, 'credit' => 50],
            ]
        );

        $this->assertFalse($result['success']);
        $this->assertSame('skipped_exists', $result['status']);
        $this->assertSame($before, AccountEntry::query()->where('order_id', $order->id)->count());
    }

    public function test_post_order_without_lines_does_not_blame_existing_invoice_when_prepaid_fails(): void
    {
        $user = $this->viewer();
        $order = $this->makeOrder('2099-05-11', 300, 'تم شحن');
        $order->prepaid_amount = 200;
        $order->save();
        $this->postInvoice($order, 300);

        $result = app(MissingSalesInvoiceJournalService::class)->postOrder($user, (int) $order->id);

        if ($result['success']) {
            $this->assertContains('prepaid', $result['lifecycle']['posted'] ?? []);
        } else {
            $this->assertStringContainsString('السداد المقدم', (string) $result['message']);
            $this->assertStringNotContainsString('إثبات الفاتورة موجود', (string) $result['message']);
        }
    }

    private function viewer(): User
    {
        $email = 'msij_' . $this->codePrefix . '@test.local';
        config(['rbac.super_admin_emails' => [$email]]);

        return User::factory()->create([
            'email' => $email,
            'department' => 'MSIJ-' . $this->codePrefix,
        ]);
    }

    private function seedAccounts(): void
    {
        $p = $this->codePrefix;
        $this->customerAcc = TreeAccount::create([
            'code' => $p . '1101',
            'name' => 'عميل قيود ناقصة',
            'type' => 'asset',
            'level' => 1,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);
        $this->salesAcc = TreeAccount::create([
            'code' => $p . '4101',
            'name' => 'إيراد قيود ناقصة',
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
                'customer_name' => 'عميل ناقص ' . $this->codePrefix,
                'customer_type' => 'افراد',
                'customer_phone_1' => '019' . substr(str_replace('.', '', uniqid('', true)), -8),
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
