<?php

namespace Tests\Feature;

use App\Models\AccountEntry;
use App\Models\Bank;
use App\Models\DailyEntry;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\TreeAccount;
use App\Services\Accounting\SalesOrderAccountingService;
use App\Services\Accounting\ShippingCourierAccountingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Test the exact scenario from the bug report:
 *
 * Order:
 *   Product value = 1000
 *   Shipping charged to customer = 200
 *   Customer paid upfront = 500
 *   Remaining should be = 700
 *
 * Expected GL entries:
 *   1) Invoice: Dr Customer 1200, Cr Sales 1000, Cr Shipping Revenue 200
 *   2) Payment: Dr Cash 500, Cr Customer 500
 *   3) Customer balance = 1200 - 500 = 700 (NEVER negative)
 *
 * Shipping cost to courier (separate):
 *   Dr Shipping Expense 200, Cr Courier Payable 200
 *   This NEVER touches the customer account.
 */
class SalesOrderAccountingTest extends TestCase
{
    use DatabaseTransactions;

    /** Unique prefix so tests don't collide with existing chart of accounts in dev DBs. */
    private string $codePrefix = '';

    private ?TreeAccount $salesRevenueAcc = null;
    private ?TreeAccount $shippingRevenueAcc = null;
    private ?TreeAccount $customerAcc = null;
    private ?TreeAccount $bankAcc = null;
    private ?TreeAccount $freightOutAcc = null;
    private ?TreeAccount $courierPayableAcc = null;
    private ?TreeAccount $customersParent = null;
    private ?Bank $bank = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->codePrefix = 'Z' . substr(str_replace('.', '', uniqid('', true)), -10);
        $this->seedAccountingTree();
    }

    private function seedAccountingTree(): void
    {
        $p = $this->codePrefix;
        $root = TreeAccount::create([
            'code' => $p . '1000',
            'name' => 'الأصول',
            'type' => 'asset',
            'level' => 1,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->customersParent = TreeAccount::create([
            'code' => $p . '1100',
            'name' => 'العملاء',
            'type' => 'asset',
            'level' => 2,
            'parent_id' => $root->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->customerAcc = TreeAccount::create([
            'code' => $p . '1100001',
            'name' => 'عميل تجريبي - 01023456789',
            'type' => 'asset',
            'level' => 4,
            'parent_id' => $this->customersParent->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->bankAcc = TreeAccount::create([
            'code' => $p . '1000211',
            'name' => 'خزينة الشركة',
            'type' => 'asset',
            'level' => 4,
            'parent_id' => $root->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $revenueRoot = TreeAccount::create([
            'code' => $p . '4000',
            'name' => 'الإيرادات',
            'type' => 'revenue',
            'level' => 1,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->salesRevenueAcc = TreeAccount::create([
            'code' => $p . '4011001',
            'name' => 'إيرادات المبيعات',
            'type' => 'revenue',
            'level' => 4,
            'parent_id' => $revenueRoot->id,
            'detail_type' => 'sales',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->shippingRevenueAcc = TreeAccount::create([
            'code' => $p . '4031001',
            'name' => 'إيراد شحن وتوصيل',
            'type' => 'revenue',
            'level' => 4,
            'parent_id' => $revenueRoot->id,
            'detail_type' => 'shipping_revenue',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $expenseRoot = TreeAccount::create([
            'code' => $p . '5000',
            'name' => 'المصروفات',
            'type' => 'expense',
            'level' => 1,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->freightOutAcc = TreeAccount::create([
            'code' => $p . '5041001',
            'name' => 'مصروف شحن صادر',
            'type' => 'expense',
            'level' => 4,
            'parent_id' => $expenseRoot->id,
            'detail_type' => 'freight_out',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $liabilityRoot = TreeAccount::create([
            'code' => $p . '2000',
            'name' => 'الخصوم',
            'type' => 'liability',
            'level' => 1,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->courierPayableAcc = TreeAccount::create([
            'code' => $p . '2031001',
            'name' => 'ذمم شركات شحن',
            'type' => 'liability',
            'level' => 4,
            'parent_id' => $liabilityRoot->id,
            'detail_type' => 'shipping_courier_payable',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->bank = Bank::create([
            'name' => 'خزينة الشركة',
            'type' => 'خزينة',
            'usage' => 'test',
            'balance' => 10000,
            'asset_id' => $this->bankAcc->id,
        ]);
    }

    /**
     * Credit amounts posted to revenue (and similar) for the order — excludes customer & bank lines.
     * Needed because {@see TreeAccount::resolveSalesRevenueAccount()} may pick a global chart account outside this test seed.
     */
    private function nonCustomerCreditAmountsForOrder(int $orderId): array
    {
        return AccountEntry::where('order_id', $orderId)
            ->where('credit', '>', 0)
            ->where('tree_account_id', '!=', $this->customerAcc->id)
            ->where('tree_account_id', '!=', $this->bankAcc->id)
            ->orderBy('credit')
            ->pluck('credit')
            ->map(fn ($v) => round((float) $v, 2))
            ->values()
            ->all();
    }

    /** @test */
    public function invoice_entry_debits_customer_for_full_amount_including_shipping()
    {
        $service = app(SalesOrderAccountingService::class);

        $order = $this->createTestOrder(
            productTotal: 1000,
            shippingRevenue: 200,
            prepaidAmount: 0,
            discount: 0
        );

        $service->recordInitialOrderRecognition($order);

        $customerEntries = AccountEntry::where('tree_account_id', $this->customerAcc->id)->get();
        $customerDebit = $customerEntries->sum('debit');
        $customerCredit = $customerEntries->sum('credit');

        // Customer should be debited 1200 (1000 product + 200 shipping)
        $this->assertEquals(1200, $customerDebit, 'Customer debit should be product + shipping = 1200');
        $this->assertEquals(0, $customerCredit, 'Customer credit should be 0 (no payment yet)');

        $revCredits = $this->nonCustomerCreditAmountsForOrder($order->id);
        $this->assertEquals([200.0, 1000.0], $revCredits, 'Revenue credits: shipping 200 + sales 1000');

        $orderJournal = AccountEntry::where('order_id', $order->id)->get();
        $this->assertEquals(
            round($orderJournal->sum('debit'), 2),
            round($orderJournal->sum('credit'), 2),
            'Order journal must balance'
        );
    }

    /** @test */
    public function prepaid_amount_correctly_reduces_customer_balance()
    {
        $service = app(SalesOrderAccountingService::class);

        $order = $this->createTestOrder(
            productTotal: 1000,
            shippingRevenue: 200,
            prepaidAmount: 500,
            discount: 0,
            bankId: $this->bank->id
        );

        $service->recordInitialOrderRecognition($order);

        $customerEntries = AccountEntry::where('tree_account_id', $this->customerAcc->id)->get();
        $customerDebit = $customerEntries->sum('debit');
        $customerCredit = $customerEntries->sum('credit');

        // Customer debit = 1200 (invoice), credit = 500 (prepaid)
        $this->assertEquals(1200, $customerDebit, 'Customer debit should be 1200');
        $this->assertEquals(500, $customerCredit, 'Customer credit should be 500 (prepaid)');

        // Net customer balance = 1200 - 500 = 700
        $customerBalance = $customerDebit - $customerCredit;
        $this->assertEquals(700, $customerBalance, 'Customer remaining balance should be 700');
    }

    /** @test */
    public function customer_balance_is_never_negative_due_to_shipping()
    {
        $service = app(SalesOrderAccountingService::class);

        $order = $this->createTestOrder(
            productTotal: 1000,
            shippingRevenue: 200,
            prepaidAmount: 500,
            discount: 0,
            bankId: $this->bank->id
        );

        $service->recordInitialOrderRecognition($order);

        $customerEntries = AccountEntry::where('tree_account_id', $this->customerAcc->id)->get();
        $balance = $customerEntries->sum('debit') - $customerEntries->sum('credit');

        $this->assertGreaterThanOrEqual(0, $balance, 'Customer balance must NEVER be negative');
        $this->assertEquals(700, $balance, 'Customer balance should be exactly 700');
    }

    /** @test */
    public function shipping_expense_never_touches_customer_account()
    {
        $service = app(SalesOrderAccountingService::class);

        $order = $this->createTestOrder(
            productTotal: 1000,
            shippingRevenue: 200,
            prepaidAmount: 500,
            discount: 0,
            bankId: $this->bank->id
        );

        $service->recordInitialOrderRecognition($order);

        $allCustomerEntries = AccountEntry::where('tree_account_id', $this->customerAcc->id)->get();

        foreach ($allCustomerEntries as $entry) {
            $this->assertStringNotContainsString('مصروف شحن', $entry->description,
                'Customer account should never have shipping expense entries');
            $this->assertStringNotContainsString('شحن صادر', $entry->description,
                'Customer account should never have freight-out entries');
            $this->assertStringNotContainsString('شركة شحن', $entry->description,
                'Customer account should never have courier entries');
        }
    }

    /** @test */
    public function courier_cost_is_separate_from_customer_ledger()
    {
        $shippingCompany = \App\Models\ShippingCompany::create([
            'name' => 'شركة شحن تجريبية',
            'type' => 'شركة',
            'balance' => 0,
            'tree_account_id' => $this->courierPayableAcc->id,
        ]);

        $order = $this->createTestOrder(
            productTotal: 1000,
            shippingRevenue: 200,
            prepaidAmount: 0,
            discount: 0
        );

        $courierService = app(ShippingCourierAccountingService::class);
        $courierService->recordShipmentCourierCost(
            order: $order,
            company: $shippingCompany,
            cost: 200.0,
            paidImmediately: false
        );

        $courierJournal = AccountEntry::where('order_id', $order->id)
            ->where('description', 'like', '%شحن صادر%')
            ->get();
        $this->assertEquals(200, $courierJournal->sum('debit'), 'Freight-out leg should be 200');
        $this->assertEquals(200, $courierJournal->sum('credit'), 'Payable/cash leg should be 200');

        // Customer account should have ZERO entries from courier cost
        $customerCourierEntries = AccountEntry::where('tree_account_id', $this->customerAcc->id)
            ->where('description', 'like', '%شحن صادر%')
            ->count();
        $this->assertEquals(0, $customerCourierEntries, 'Customer should have zero courier-related entries');
    }

    /** @test */
    public function full_scenario_product_1000_shipping_200_paid_500_remaining_700()
    {
        $service = app(SalesOrderAccountingService::class);

        // Step 1: Create order with product=1000, shipping=200, prepaid=500
        $order = $this->createTestOrder(
            productTotal: 1000,
            shippingRevenue: 200,
            prepaidAmount: 500,
            discount: 0,
            bankId: $this->bank->id
        );

        $service->recordInitialOrderRecognition($order);

        // Verify all GL entries for this order only (DB may contain unrelated journals)
        $customerEntries = AccountEntry::where('tree_account_id', $this->customerAcc->id)->get();
        $bankEntries = AccountEntry::where('tree_account_id', $this->bankAcc->id)->where('order_id', $order->id)->get();

        // Customer: Dr 1200, Cr 500 → Balance = 700
        $this->assertEquals(1200, $customerEntries->sum('debit'));
        $this->assertEquals(500, $customerEntries->sum('credit'));
        $this->assertEquals(700, $customerEntries->sum('debit') - $customerEntries->sum('credit'));

        $revCredits = $this->nonCustomerCreditAmountsForOrder($order->id);
        $this->assertEquals([200.0, 1000.0], $revCredits);

        // Bank: Dr 500 (received prepaid)
        $this->assertEquals(500, $bankEntries->sum('debit'));

        $orderEntries = AccountEntry::where('order_id', $order->id)->get();
        $this->assertEquals(
            round($orderEntries->sum('debit'), 2),
            round($orderEntries->sum('credit'), 2),
            'Order journal must balance'
        );
    }

    /** @test */
    public function discount_reduces_product_sales_not_shipping()
    {
        $service = app(SalesOrderAccountingService::class);

        $order = $this->createTestOrder(
            productTotal: 1000,
            shippingRevenue: 200,
            prepaidAmount: 0,
            discount: 100
        );

        $service->recordInitialOrderRecognition($order);

        $customerEntries = AccountEntry::where('tree_account_id', $this->customerAcc->id)->get();

        // Customer debit = (1000 - 100) + 200 = 1100
        $this->assertEquals(1100, $customerEntries->sum('debit'), 'Customer debit = net product + shipping');

        $revCredits = $this->nonCustomerCreditAmountsForOrder($order->id);
        $this->assertEquals([200.0, 900.0], $revCredits, 'Shipping 200 + net sales 900');
    }

    /** @test */
    public function collection_zeros_out_customer_balance()
    {
        $service = app(SalesOrderAccountingService::class);

        $order = $this->createTestOrder(
            productTotal: 1000,
            shippingRevenue: 200,
            prepaidAmount: 500,
            discount: 0,
            bankId: $this->bank->id
        );

        // Step 1: Invoice recognition (Dr Customer 1200, Cr Sales 1000, Cr ShipRev 200)
        // + Prepaid (Dr Bank 500, Cr Customer 500) → Customer balance = 700
        $service->recordInitialOrderRecognition($order);

        $balanceBefore = AccountEntry::where('tree_account_id', $this->customerAcc->id)
            ->selectRaw('SUM(debit) - SUM(credit) as bal')->value('bal');
        $this->assertEquals(700, (float) $balanceBefore, 'Before collection: customer balance = 700');

        // Step 2: Simulate collection — shipping company returns net_total (700)
        // This is what postCustomerCollectionAccounting does: Dr Bank 700, Cr Customer 700
        $collectionAmount = (float) $order->net_total; // 700

        $collectionDaily = DailyEntry::create([
            'date' => now(),
            'entry_number' => DailyEntry::getNextEntryNumber(),
            'description' => 'تحصيل من العميل — طلب ' . $order->id,
            'user_id' => 1,
        ]);

        AccountEntry::create([
            'tree_account_id' => $this->bankAcc->id,
            'debit' => $collectionAmount,
            'credit' => 0,
            'description' => 'تحصيل من شركة شحن',
            'daily_entry_id' => $collectionDaily->id,
            'order_id' => $order->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        AccountEntry::create([
            'tree_account_id' => $this->customerAcc->id,
            'debit' => 0,
            'credit' => $collectionAmount,
            'description' => 'سداد ذمم العملاء',
            'daily_entry_id' => $collectionDaily->id,
            'order_id' => $order->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Customer balance should now be ZERO
        $balanceAfter = AccountEntry::where('tree_account_id', $this->customerAcc->id)
            ->selectRaw('SUM(debit) - SUM(credit) as bal')->value('bal');
        $this->assertEquals(0, (float) $balanceAfter, 'After full collection: customer balance = 0');
    }

    /** @test */
    public function order_total_2000_prepaid_500_customer_balance_1500_before_final_collection()
    {
        $service = app(SalesOrderAccountingService::class);

        $order = $this->createTestOrder(
            productTotal: 1800,
            shippingRevenue: 200,
            prepaidAmount: 500,
            discount: 0,
            bankId: $this->bank->id
        );

        $this->assertEquals(2000, (float) $order->total_invoice);
        $this->assertEquals(1500, (float) $order->net_total);

        $service->recordInitialOrderRecognition($order);

        $bal = (float) AccountEntry::where('tree_account_id', $this->customerAcc->id)
            ->selectRaw('SUM(debit) - SUM(credit) as bal')->value('bal');

        $this->assertEquals(1500, $bal, 'AR sub-ledger = invoice 2000 − prepaid 500 = 1500');
    }

    /** @test */
    public function courier_cost_paid_from_company_treasury_not_customer()
    {
        $service = app(SalesOrderAccountingService::class);

        $shippingCompany = \App\Models\ShippingCompany::create([
            'name' => 'مندوب تجريبي',
            'type' => 'مندوب',
            'balance' => 0,
            'tree_account_id' => $this->courierPayableAcc->id,
        ]);

        $order = $this->createTestOrder(
            productTotal: 1000,
            shippingRevenue: 200,
            prepaidAmount: 500,
            discount: 0,
            bankId: $this->bank->id
        );

        // Step 1: Invoice + prepaid
        $service->recordInitialOrderRecognition($order);

        // Step 2: Record courier cost (150 — less than what customer paid for shipping)
        $courierService = app(ShippingCourierAccountingService::class);
        $courierService->recordShipmentCourierCost(
            order: $order,
            company: $shippingCompany,
            cost: 150.0,
            paidImmediately: false
        );

        // Customer balance should still be 700 (unaffected by courier cost)
        $customerBalance = AccountEntry::where('tree_account_id', $this->customerAcc->id)
            ->selectRaw('SUM(debit) - SUM(credit) as bal')->value('bal');
        $this->assertEquals(700, (float) $customerBalance,
            'Courier cost must NOT affect customer balance');

        // Shipping profit = 200 (revenue) - 150 (expense) = 50 (resolved accounts may be outside seed IDs)
        $shippingRevenue = (float) AccountEntry::where('order_id', $order->id)
            ->where('description', 'like', '%إيراد شحن%')
            ->sum('credit');
        $freightExpense = (float) AccountEntry::where('order_id', $order->id)
            ->where('description', 'like', '%مصروف توصيل%')
            ->sum('debit');
        $shippingProfit = $shippingRevenue - $freightExpense;
        $this->assertEquals(50, $shippingProfit, 'Shipping profit = revenue 200 - expense 150 = 50');

        $courierPayable = (float) AccountEntry::where('order_id', $order->id)
            ->where('description', 'like', '%شحن صادر%')
            ->where('credit', '>', 0)
            ->sum('credit');
        $this->assertEquals(150, $courierPayable, 'Courier payable leg = 150');
    }

    private function createTestOrder(
        float $productTotal,
        float $shippingRevenue,
        float $prepaidAmount,
        float $discount,
        ?int $bankId = null
    ): Order {
        $totalInvoice = $productTotal + $shippingRevenue;
        // net_total = total_invoice - prepaid - discount (matches frontend formula)
        $netTotal = $totalInvoice - $prepaidAmount - $discount;

        // Avoid double GL posting: tests call SalesOrderAccountingService explicitly.
        $order = Order::withoutEvents(function () use (
            $productTotal,
            $shippingRevenue,
            $totalInvoice,
            $prepaidAmount,
            $discount,
            $netTotal,
            $bankId
        ) {
            return Order::create([
                'customer_name' => 'عميل تجريبي',
                'customer_type' => 'افراد',
                'customer_phone_1' => '01023456789',
                'customer_phone_2' => '',
                'tel' => '',
                'governorate' => 'القاهرة',
                'city' => 'مدينة نصر',
                'address' => 'شارع تجريبي',
                'order_date' => now()->toDateString(),
                'shipping_method_id' => 1,
                'order_source_id' => 1,
                'order_type' => 'طلب عادي',
                'shipping_cost' => $shippingRevenue,
                'shipping_revenue' => $shippingRevenue,
                'total_invoice' => $totalInvoice,
                'prepaid_amount' => $prepaidAmount,
                'discount' => $discount,
                'net_total' => $netTotal,
                'bank_id' => $bankId,
                'order_status' => 'طلب جديد',
            ]);
        });

        OrderProduct::create([
            'order_id' => $order->id,
            'category_id' => 1,
            'quantity' => 1,
            'price' => $productTotal,
            'total_price' => $productTotal,
            'special_details' => null,
        ]);

        return $order;
    }
}
