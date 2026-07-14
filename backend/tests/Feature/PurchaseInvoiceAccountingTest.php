<?php

namespace Tests\Feature;

use App\Enums\PurchaseInvoiceKind;
use App\Models\Bank;
use App\Models\Category;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\DocumentSequence;
use App\Models\Purchase;
use App\Models\ShippingCompany;
use App\Models\Stock;
use App\Models\StockTransaction;
use App\Models\Supplier;
use App\Models\TransactionType;
use App\Models\TreeAccount;
use App\Models\User;
use App\Services\Accounting\InventoryGlPostingService;
use App\Services\Purchases\PurchaseDeletionService;
use App\Services\Purchases\PurchaseInvoiceAccountingService;
use App\Services\Purchases\PurchaseInvoiceTypeResolver;
use App\Services\Stock\PurchaseStockDocumentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaseInvoiceAccountingTest extends TestCase
{
    use DatabaseTransactions;

    private string $codePrefix = '';

    private User $user;

    private TreeAccount $inventoryAcc;

    private TreeAccount $supplierAcc;

    private TreeAccount $freightInAcc;

    private TreeAccount $cogsAcc;

    private TreeAccount $bankAcc;

    private Bank $bank;

    private Supplier $supplier;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->codePrefix = 'P'.substr(str_replace('.', '', uniqid('', true)), -9);
        $this->user = User::where('department', 'Admin')->first()
            ?? User::factory()->create(['department' => 'Admin']);
        $this->actingAs($this->user, 'api');

        $this->seedChart();
        $this->seedTransactionTypes();
        $this->seedSupplierAndCategory();
    }

    public function test_purchase_receipt_increases_stock_and_supplier_balance_with_gl(): void
    {
        $purchase = $this->createPurchase('اضافة وارد جديد', 10, 50, 0, 500);

        $this->category->refresh();
        $this->supplier->refresh();

        $this->assertEqualsWithDelta(10.0, (float) $this->category->quantity, 0.001);
        $this->assertEqualsWithDelta(500.0, (float) $this->supplier->balance, 0.01);

        $this->assertGlLine($this->inventoryAcc->id, 500.0, 0.0);
        $this->assertGlLine($this->supplierAcc->id, 0.0, 500.0);

        $doc = StockTransaction::query()
            ->where('reference_type', 'purchase')
            ->where('reference_id', $purchase->id)
            ->first();
        $this->assertNotNull($doc);
        $this->assertSame(
            TransactionType::query()->where('code', 'PURCHASE_ADD')->value('id'),
            $doc->transaction_type_id
        );
    }

    public function test_purchase_receipt_with_freight_splits_gl_between_supplier_and_inventory(): void
    {
        $freightDebitBefore = (float) DailyEntryItem::query()
            ->whereHas('account', fn ($q) => $q->where('detail_type', 'freight_in'))
            ->sum('debit');

        $this->createPurchase('اضافة وارد جديد', 5, 100, 50, 550, 0, 550);

        $this->supplier->refresh();
        $this->assertEqualsWithDelta(550.0, (float) $this->supplier->balance, 0.01);

        $this->assertGlLine($this->inventoryAcc->id, 500.0, 0.0);
        $this->assertGlLine($this->supplierAcc->id, 0.0, 550.0);

        $freightDebitAdded = (float) DailyEntryItem::query()
            ->whereHas('account', fn ($q) => $q->where('detail_type', 'freight_in'))
            ->sum('debit') - $freightDebitBefore;
        $this->assertEqualsWithDelta(50.0, $freightDebitAdded, 0.02);
    }

    public function test_purchase_freight_with_shipping_rep_credits_payable_not_receivable_account(): void
    {
        $p = $this->codePrefix;
        $payableParent = TreeAccount::create([
            'code' => $p.'2200',
            'name' => 'ذمم شركات شحن اختبار',
            'type' => 'liability',
            'level' => 2,
            'detail_type' => 'shipping_courier_payable',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $receivableParent = TreeAccount::create([
            'code' => $p.'1200',
            'name' => 'مناديب اختبار',
            'type' => 'asset',
            'level' => 2,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $repReceivable = TreeAccount::create([
            'code' => $p.'1201',
            'name' => 'مندوب شحن اختبار',
            'type' => 'asset',
            'level' => 3,
            'parent_id' => $receivableParent->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $rep = ShippingCompany::create([
            'name' => 'مندوب شحن '.$p,
            'type' => 'مندوب',
            'receivable_tree_account_id' => $repReceivable->id,
            'refused_orders_percentage' => 0,
        ]);

        $this->createPurchase('اضافة وارد جديد', 5, 100, 40, 540, 40, 500, $rep->id);

        $rep->refresh();
        $repReceivable->refresh();

        $this->assertNotNull($rep->tree_account_id, 'Freight should create/link a payable tree account');
        $this->assertNotSame($repReceivable->id, (int) $rep->tree_account_id);

        $this->assertGlLine($repReceivable->id, 0.0, 0.0);
        $this->assertGlLine((int) $rep->tree_account_id, 0.0, 40.0);
    }

    public function test_sales_return_increases_stock_without_supplier_balance_or_ap_gl(): void
    {
        $inventoryBefore = $this->glNet($this->inventoryAcc->id);
        $supplierBefore = (float) $this->supplier->balance;

        $this->createPurchase('اضافة وارد جديد', 10, 50, 0, 500);
        $this->supplier->update(['balance' => 500]);

        $this->createPurchase('مرتجع مبيعات', 3, 50, 0, 150);

        $this->category->refresh();
        $this->supplier->refresh();

        $this->assertEqualsWithDelta(13.0, (float) $this->category->quantity, 0.001);
        $this->assertEqualsWithDelta($supplierBefore + 500.0, (float) $this->supplier->balance, 0.01);
        $this->assertEqualsWithDelta($inventoryBefore + 650.0, $this->glNet($this->inventoryAcc->id), 0.02);

        $cogsCredit = (float) DailyEntryItem::query()
            ->whereHas('account', fn ($q) => $q->where('detail_type', 'cogs'))
            ->sum('credit');
        $this->assertGreaterThan(0.0, $cogsCredit);
    }

    public function test_purchase_return_decreases_stock_and_supplier_balance(): void
    {
        $this->createPurchase('اضافة وارد جديد', 10, 50, 0, 500);
        $this->supplier->update(['balance' => 500]);

        $purchase = $this->createPurchase('مرتجع', 4, 50, 0, 200, 0, 200);

        $this->category->refresh();
        $this->supplier->refresh();

        $this->assertEqualsWithDelta(6.0, (float) $this->category->quantity, 0.001);
        $this->assertEqualsWithDelta(300.0, (float) $this->supplier->balance, 0.01);

        $doc = StockTransaction::query()
            ->where('reference_type', 'purchase')
            ->where('reference_id', $purchase->id)
            ->first();
        $this->assertSame(
            TransactionType::query()->where('code', 'PURCHASE_RETURN')->value('id'),
            $doc?->transaction_type_id
        );
    }

    public function test_amanat_increases_stock_without_supplier_or_inventory_gl(): void
    {
        $supplierBefore = (float) $this->supplier->balance;

        $purchase = $this->createPurchase('امانات', 5, 40, 0, 200);

        $this->category->refresh();
        $this->supplier->refresh();

        $this->assertEqualsWithDelta(5.0, (float) $this->category->quantity, 0.001);
        $this->assertEqualsWithDelta($supplierBefore, (float) $this->supplier->balance, 0.01);

        $inventoryGl = DailyEntryItem::query()
            ->where('account_id', $this->inventoryAcc->id)
            ->sum(DB::raw('debit - credit'));
        $this->assertEqualsWithDelta(0.0, (float) $inventoryGl, 0.01);
    }

    public function test_http_store_endpoint_accepts_positive_quantities_for_purchase_return(): void
    {
        $this->createPurchase('اضافة وارد جديد', 10, 50, 0, 500);
        $this->supplier->update(['balance' => 500]);

        $response = $this->postJson('/api/purchases', [
            'supplier_id' => $this->supplier->id,
            'invoice_type' => 'مرتجع',
            'receipt_date' => now()->toDateString(),
            'total_price' => 200,
            'paid_amount' => 0,
            'due_amount' => 200,
            'transport_cost' => 0,
            'price_edited' => 0,
            'products' => [
                [
                    'product_name' => $this->category->category_name,
                    'product_unit' => 'قطعة',
                    'product_quantity' => 4,
                    'product_price' => 50,
                    'total' => 200,
                    'price_edited' => false,
                    'category_id' => $this->category->id,
                ],
            ],
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->category->refresh();
        $this->assertEqualsWithDelta(6.0, (float) $this->category->quantity, 0.001);
    }

    public function test_deleting_purchase_receipt_reverses_stock_and_supplier_balance(): void
    {
        $purchase = $this->createPurchase('اضافة وارد جديد', 8, 25, 0, 200);
        $mainId = (int) $purchase->id;

        app(PurchaseDeletionService::class)->delete($mainId, (int) $this->user->id);

        $this->category->refresh();
        $this->supplier->refresh();

        $this->assertEqualsWithDelta(0.0, (float) $this->category->quantity, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $this->supplier->balance, 0.01);
        $this->assertSame('1', (string) Purchase::find($mainId)->status);

        $this->assertNull(
            StockTransaction::query()
                ->where('reference_type', 'purchase')
                ->where('reference_id', $purchase->id)
                ->first()
        );
    }

    public function test_edit_purchase_receipt_reverses_old_and_applies_new_totals(): void
    {
        $purchase = $this->createPurchase('اضافة وارد جديد', 10, 50, 0, 500);
        $mainId = (int) $purchase->id;

        $response = $this->postJson('/api/purchases', [
            'invoiceId' => $mainId,
            'supplier_id' => $this->supplier->id,
            'invoice_type' => 'تم الاستلام',
            'receipt_date' => now()->toDateString(),
            'total_price' => 300,
            'paid_amount' => 0,
            'due_amount' => 300,
            'transport_cost' => 0,
            'price_edited' => 0,
            'payment_type' => 'bank',
            'bank_id' => $this->bank->id,
            'products' => [
                [
                    'product_name' => $this->category->category_name,
                    'product_unit' => 'قطعة',
                    'product_quantity' => 6,
                    'product_price' => 50,
                    'total' => 300,
                    'price_edited' => false,
                    'category_id' => $this->category->id,
                ],
            ],
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $tracking = DB::table('purchases_trackings')
            ->where('invoice_id', $mainId)
            ->latest('id')
            ->first();
        $this->assertNotNull($tracking);
        $this->assertSame('تعديل فاتورة', $tracking->action);
        $this->assertNotEmpty($tracking->details);
        $this->assertStringContainsString('المستخدم:', (string) $tracking->details);
        $this->assertStringContainsString('التاريخ:', (string) $tracking->details);
        $this->assertStringContainsString('بنود الفاتورة:', (string) $tracking->details);

        $this->category->refresh();
        $this->supplier->refresh();

        $this->assertEqualsWithDelta(6.0, (float) $this->category->quantity, 0.001);
        $this->assertEqualsWithDelta(300.0, (float) $this->supplier->balance, 0.01);
        $this->assertEqualsWithDelta(300.0, $this->glNet($this->inventoryAcc->id), 0.02);
        $this->assertEqualsWithDelta(-300.0, $this->glNet($this->supplierAcc->id), 0.02);
    }

    public function test_edit_purchase_receipt_uses_tadil_label_and_keeps_balanced_journals(): void
    {
        $purchase = $this->createPurchase('اضافة وارد جديد', 10, 50, 0, 500);
        $mainId = (int) $purchase->id;
        $invoiceNumber = $purchase->invoice_number;

        $response = $this->postJson('/api/purchases', [
            'invoiceId' => $mainId,
            'supplier_id' => $this->supplier->id,
            'invoice_type' => 'تم الاستلام',
            'receipt_date' => now()->toDateString(),
            'total_price' => 300,
            'paid_amount' => 0,
            'due_amount' => 300,
            'transport_cost' => 0,
            'price_edited' => 0,
            'payment_type' => 'bank',
            'bank_id' => $this->bank->id,
            'products' => [
                [
                    'product_name' => $this->category->category_name,
                    'product_unit' => 'قطعة',
                    'product_quantity' => 6,
                    'product_price' => 50,
                    'total' => 300,
                    'price_edited' => false,
                    'category_id' => $this->category->id,
                ],
            ],
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertTrue(
            DailyEntry::query()
                ->where('description', 'تعديل — استلام مشتريات — فاتورة '.$invoiceNumber)
                ->exists(),
            'Edit reversal journal should use تعديل label, not عكس'
        );

        $this->assertFalse(
            DailyEntry::query()
                ->where('description', 'عكس — استلام مشتريات — فاتورة '.$invoiceNumber)
                ->exists()
        );

        $relatedEntries = DailyEntry::query()
            ->where('description', 'like', '%'.$invoiceNumber.'%')
            ->pluck('id');

        foreach ($relatedEntries as $entryId) {
            $debit = (float) DailyEntryItem::query()->where('daily_entry_id', $entryId)->sum('debit');
            $credit = (float) DailyEntryItem::query()->where('daily_entry_id', $entryId)->sum('credit');
            $this->assertEqualsWithDelta($debit, $credit, 0.02, "Journal {$entryId} must be balanced");
        }

        $this->assertEqualsWithDelta(300.0, $this->glNet($this->inventoryAcc->id), 0.02);
        $this->assertEqualsWithDelta(-300.0, $this->glNet($this->supplierAcc->id), 0.02);
    }

    public function test_edit_purchase_receipt_with_partial_payment_reverses_old_payment_and_applies_new(): void
    {
        $bankStart = 10000.0;
        $this->bank->update(['balance' => $bankStart]);

        $purchase = $this->createPurchase('اضافة وارد جديد', 10, 50, 0, 500, 200, 300);
        $mainId = (int) $purchase->id;
        $invoiceNumber = $purchase->invoice_number;

        $this->supplier->refresh();
        $this->bank->refresh();
        $this->assertEqualsWithDelta(300.0, (float) $this->supplier->balance, 0.01);
        $this->assertEqualsWithDelta(9800.0, (float) $this->bank->balance, 0.01);
        $this->assertEqualsWithDelta(-300.0, $this->glNet($this->supplierAcc->id), 0.02);
        $this->assertEqualsWithDelta(-200.0, $this->glNet($this->bankAcc->id), 0.02);

        $response = $this->postJson('/api/purchases', [
            'invoiceId' => $mainId,
            'supplier_id' => $this->supplier->id,
            'invoice_type' => 'تم الاستلام',
            'receipt_date' => now()->toDateString(),
            'total_price' => 400,
            'paid_amount' => 150,
            'due_amount' => 250,
            'transport_cost' => 0,
            'price_edited' => 0,
            'payment_type' => 'bank',
            'bank_id' => $this->bank->id,
            'products' => [
                [
                    'product_name' => $this->category->category_name,
                    'product_unit' => 'قطعة',
                    'product_quantity' => 8,
                    'product_price' => 50,
                    'total' => 400,
                    'price_edited' => false,
                    'category_id' => $this->category->id,
                ],
            ],
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->category->refresh();
        $this->supplier->refresh();
        $this->bank->refresh();

        $this->assertEqualsWithDelta(8.0, (float) $this->category->quantity, 0.001);
        $this->assertEqualsWithDelta(250.0, (float) $this->supplier->balance, 0.01);
        $this->assertEqualsWithDelta(9850.0, (float) $this->bank->balance, 0.01);
        $this->assertEqualsWithDelta(400.0, $this->glNet($this->inventoryAcc->id), 0.02);
        $this->assertEqualsWithDelta(-250.0, $this->glNet($this->supplierAcc->id), 0.02);
        $this->assertEqualsWithDelta(-150.0, $this->glNet($this->bankAcc->id), 0.02);

        $this->assertTrue(
            DailyEntry::query()
                ->where('description', 'عكس سداد مشتريات — '.$invoiceNumber)
                ->exists(),
            'Old payment GL should be reversed on edit'
        );
        $this->assertTrue(
            DailyEntry::query()
                ->where('description', 'سداد مشتريات — '.$invoiceNumber)
                ->exists(),
            'New payment GL should be posted after edit'
        );

        $paymentEntries = DailyEntry::query()
            ->where('description', 'like', '%'.$invoiceNumber.'%')
            ->where(function ($q) {
                $q->where('description', 'like', 'سداد%')
                    ->orWhere('description', 'like', 'عكس سداد%')
                    ->orWhere('description', 'like', 'تعديل —%');
            })
            ->pluck('id');

        foreach ($paymentEntries as $entryId) {
            $debit = (float) DailyEntryItem::query()->where('daily_entry_id', $entryId)->sum('debit');
            $credit = (float) DailyEntryItem::query()->where('daily_entry_id', $entryId)->sum('credit');
            $this->assertEqualsWithDelta($debit, $credit, 0.02, "Payment journal {$entryId} must be balanced");
        }
    }

    public function test_delete_purchase_receipt_uses_aks_label_on_reversal(): void
    {
        $purchase = $this->createPurchase('اضافة وارد جديد', 8, 25, 0, 200);
        $invoiceNumber = $purchase->invoice_number;

        app(PurchaseDeletionService::class)->delete((int) $purchase->id, (int) $this->user->id);

        $this->assertTrue(
            DailyEntry::query()
                ->where('description', 'عكس — استلام مشتريات — فاتورة '.$invoiceNumber)
                ->exists(),
            'Delete reversal journal should keep عكس label'
        );
    }

    public function test_delete_purchase_succeeds_when_stock_was_partially_consumed(): void
    {
        $purchase = $this->createPurchase('اضافة وارد جديد', 10, 50, 0, 500);
        $mainId = (int) $purchase->id;

        DB::table('categories')->where('id', $this->category->id)->update(['quantity' => 0]);
        $this->category->refresh();

        $result = app(PurchaseDeletionService::class)->delete($mainId, (int) $this->user->id);

        $this->assertNotEmpty($result['stock_warnings']);

        $main = Purchase::query()->findOrFail($mainId);
        $this->assertSame('1', (string) $main->status);

        $tracking = DB::table('purchases_trackings')
            ->where('invoice_id', $mainId)
            ->where('action', 'حذف فاتورة')
            ->latest('id')
            ->first();
        $this->assertNotNull($tracking);
        $this->assertStringContainsString('المخزون:', (string) $tracking->details);

        $this->category->refresh();
        $this->assertEqualsWithDelta(-10.0, (float) $this->category->quantity, 0.001);
    }

    public function test_delete_purchase_allows_negative_stock_when_partially_consumed(): void
    {
        $purchase = $this->createPurchase('اضافة وارد جديد', 10, 50, 0, 500);
        $mainId = (int) $purchase->id;

        DB::table('categories')->where('id', $this->category->id)->update(['quantity' => 3]);
        $this->category->refresh();

        $result = app(PurchaseDeletionService::class)->delete($mainId, (int) $this->user->id);

        $this->assertNotEmpty($result['stock_warnings']);
        $this->assertStringContainsString('خصم سالب', $result['stock_warnings'][0]);

        $this->category->refresh();
        $this->assertEqualsWithDelta(-7.0, (float) $this->category->quantity, 0.001);
    }

    public function test_edit_purchase_receipt_succeeds_when_original_qty_was_partially_consumed(): void
    {
        $purchase = $this->createPurchase('اضافة وارد جديد', 100, 50, 0, 5000);
        $mainId = (int) $purchase->id;

        DB::table('categories')->where('id', $this->category->id)->update(['quantity' => 50]);
        $this->category->refresh();

        $response = $this->postJson('/api/purchases', [
            'invoiceId' => $mainId,
            'supplier_id' => $this->supplier->id,
            'invoice_type' => 'تم الاستلام',
            'receipt_date' => now()->toDateString(),
            'total_price' => 5000,
            'paid_amount' => 0,
            'due_amount' => 5000,
            'transport_cost' => 0,
            'price_edited' => 0,
            'payment_type' => 'bank',
            'bank_id' => $this->bank->id,
            'products' => [
                [
                    'product_name' => $this->category->category_name,
                    'product_unit' => 'قطعة',
                    'product_quantity' => 100,
                    'product_price' => 50,
                    'total' => 5000,
                    'price_edited' => false,
                    'category_id' => $this->category->id,
                ],
            ],
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->category->refresh();
        $this->assertEqualsWithDelta(50.0, (float) $this->category->quantity, 0.001);
    }

    public function test_edit_purchase_receipt_succeeds_when_reducing_below_available_stock_with_partial_deduction(): void
    {
        $purchase = $this->createPurchase('اضافة وارد جديد', 100, 50, 0, 5000);
        $mainId = (int) $purchase->id;

        DB::table('categories')->where('id', $this->category->id)->update(['quantity' => 50]);
        $this->category->refresh();

        $response = $this->postJson('/api/purchases', [
            'invoiceId' => $mainId,
            'supplier_id' => $this->supplier->id,
            'invoice_type' => 'تم الاستلام',
            'receipt_date' => now()->toDateString(),
            'total_price' => 1500,
            'paid_amount' => 0,
            'due_amount' => 1500,
            'transport_cost' => 0,
            'price_edited' => 0,
            'payment_type' => 'bank',
            'bank_id' => $this->bank->id,
            'products' => [
                [
                    'product_name' => $this->category->category_name,
                    'product_unit' => 'قطعة',
                    'product_quantity' => 30,
                    'product_price' => 50,
                    'total' => 1500,
                    'price_edited' => false,
                    'category_id' => $this->category->id,
                ],
            ],
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertNotEmpty($response->json('warnings'));

        $tracking = DB::table('purchases_trackings')
            ->where('invoice_id', $mainId)
            ->latest('id')
            ->first();
        $this->assertNotNull($tracking);
        $this->assertStringContainsString('المخزون:', (string) $tracking->details);

        $this->category->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $this->category->quantity, 0.001);
    }

    public function test_edit_purchase_changes_type_to_sales_return_and_unwinds_supplier_balance(): void
    {
        $purchase = $this->createPurchase('اضافة وارد جديد', 8, 50, 0, 400);
        $mainId = (int) $purchase->id;
        $this->supplier->refresh();
        $this->assertEqualsWithDelta(400.0, (float) $this->supplier->balance, 0.01);

        $response = $this->postJson('/api/purchases', [
            'invoiceId' => $mainId,
            'supplier_id' => $this->supplier->id,
            'invoice_type' => 'مرتجع مبيعات',
            'receipt_date' => now()->toDateString(),
            'total_price' => 150,
            'paid_amount' => 0,
            'due_amount' => 0,
            'transport_cost' => 0,
            'price_edited' => 0,
            'payment_type' => 'bank',
            'bank_id' => $this->bank->id,
            'products' => [
                [
                    'product_name' => $this->category->category_name,
                    'product_unit' => 'قطعة',
                    'product_quantity' => 3,
                    'product_price' => 50,
                    'total' => 150,
                    'price_edited' => false,
                    'category_id' => $this->category->id,
                ],
            ],
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->category->refresh();
        $this->supplier->refresh();

        $this->assertEqualsWithDelta(3.0, (float) $this->category->quantity, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $this->supplier->balance, 0.01);
    }

    public function test_edit_purchase_with_freight_updates_shipping_rep_payable(): void
    {
        $p = $this->codePrefix;

        $rep = ShippingCompany::create([
            'name' => 'مندوب تعديل '.$p,
            'type' => 'مندوب',
            'refused_orders_percentage' => 0,
        ]);

        $purchase = $this->createPurchase('اضافة وارد جديد', 5, 100, 0, 500);
        $mainId = (int) $purchase->id;

        $response = $this->postJson('/api/purchases', [
            'invoiceId' => $mainId,
            'supplier_id' => $this->supplier->id,
            'shipping_company_id' => $rep->id,
            'invoice_type' => 'تم الاستلام',
            'receipt_date' => now()->toDateString(),
            'total_price' => 560,
            'paid_amount' => 0,
            'due_amount' => 500,
            'transport_cost' => 60,
            'price_edited' => 0,
            'payment_type' => 'bank',
            'bank_id' => $this->bank->id,
            'products' => [
                [
                    'product_name' => $this->category->category_name,
                    'product_unit' => 'قطعة',
                    'product_quantity' => 5,
                    'product_price' => 100,
                    'total' => 500,
                    'price_edited' => false,
                    'category_id' => $this->category->id,
                ],
            ],
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $rep->refresh();
        $this->assertNotNull($rep->tree_account_id);
        $repPayableNet = (float) DailyEntryItem::query()
            ->where('account_id', $rep->tree_account_id)
            ->sum(DB::raw('credit - debit'));
        $this->assertEqualsWithDelta(60.0, $repPayableNet, 0.02);
        $this->assertEqualsWithDelta(-500.0, $this->glNet($this->supplierAcc->id), 0.02);
    }

    private function createPurchase(
        string $invoiceType,
        float $qty,
        float $unitPrice,
        float $transport,
        float $grandTotal,
        float $paid = 0,
        float $due = null,
        ?int $shippingCompanyId = null,
    ): Purchase {
        $due ??= $grandTotal - $paid;
        $lineTotal = $qty * $unitPrice;

        $purchase = Purchase::create([
            'supplier_id' => $this->supplier->id,
            'shipping_company_id' => $shippingCompanyId,
            'invoice_type' => $invoiceType,
            'receipt_date' => now()->toDateString(),
            'invoice_number' => 'T-'.$this->codePrefix.'-'.uniqid(),
            'invoice_no' => 'T-'.$this->codePrefix.'-'.uniqid(),
            'total_price' => $grandTotal,
            'paid_amount' => $paid,
            'due_amount' => $due,
            'transport_cost' => $transport,
            'product_total' => $lineTotal,
            'shipping_total' => $transport,
            'grand_total' => $grandTotal,
            'price_edited' => 0,
            'invoice_image' => '',
            'payment_type' => 'bank',
            'bank_id' => $this->bank->id,
            'status' => null,
        ]);

        $resolver = app(PurchaseInvoiceTypeResolver::class);
        $kind = $resolver->kind($invoiceType);
        $accounting = app(PurchaseInvoiceAccountingService::class);

        $products = [[
            'product_name' => $this->category->category_name,
            'product_unit' => 'قطعة',
            'product_quantity' => $qty,
            'product_price' => $unitPrice,
            'total' => $lineTotal,
            'price_edited' => false,
            'category_id' => $this->category->id,
        ]];

        $result = $accounting->applyProductLines($purchase, $products, $kind);

        $accounting->adjustSupplierBalances(
            $this->supplier,
            $kind,
            (float) $due,
            $grandTotal,
            (int) $purchase->id,
        );

        $accounting->postGlForPurchase(
            $purchase,
            $this->supplier,
            $result['inventory_gl'],
            $transport,
            abs($result['lines_sum']),
            $kind,
            (int) $this->user->id,
        );

        if ($paid > 0.00001) {
            $this->bank->decrement('balance', $paid);
            app(InventoryGlPostingService::class)->postPurchasePaymentGl(
                $purchase,
                $this->supplier,
                $paid,
                (int) $this->user->id,
            );
        }

        app(PurchaseStockDocumentService::class)->syncPurchaseDocument($purchase->fresh());

        return $purchase->fresh();
    }

    private function assertGlLine(int $accountId, float $expectedDebit, float $expectedCredit): void
    {
        $debit = (float) DailyEntryItem::query()->where('account_id', $accountId)->sum('debit');
        $credit = (float) DailyEntryItem::query()->where('account_id', $accountId)->sum('credit');

        $this->assertEqualsWithDelta($expectedDebit, $debit, 0.02, "Debit mismatch for account {$accountId}");
        $this->assertEqualsWithDelta($expectedCredit, $credit, 0.02, "Credit mismatch for account {$accountId}");
    }

    private function glNet(int $accountId): float
    {
        return (float) DailyEntryItem::query()
            ->where('account_id', $accountId)
            ->sum(DB::raw('debit - credit'));
    }

    private function seedChart(): void
    {
        $p = $this->codePrefix;

        $assetRoot = TreeAccount::create([
            'code' => $p.'1000',
            'name' => 'أصول اختبار مشتريات',
            'type' => 'asset',
            'level' => 1,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->inventoryAcc = TreeAccount::create([
            'code' => $p.'1101',
            'name' => 'مخزون خام اختبار',
            'type' => 'asset',
            'level' => 3,
            'parent_id' => $assetRoot->id,
            'detail_type' => 'inventory',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->bankAcc = TreeAccount::create([
            'code' => $p.'1102',
            'name' => 'بنك اختبار مشتريات',
            'type' => 'asset',
            'level' => 3,
            'parent_id' => $assetRoot->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $liabilityRoot = TreeAccount::create([
            'code' => $p.'2000',
            'name' => 'خصوم اختبار',
            'type' => 'liability',
            'level' => 1,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->supplierAcc = TreeAccount::create([
            'code' => $p.'2101',
            'name' => 'مورد اختبار',
            'type' => 'liability',
            'level' => 3,
            'parent_id' => $liabilityRoot->id,
            'detail_type' => 'supplier',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $expenseRoot = TreeAccount::create([
            'code' => $p.'5000',
            'name' => 'مصروفات اختبار',
            'type' => 'expense',
            'level' => 1,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->freightInAcc = TreeAccount::create([
            'code' => $p.'5101',
            'name' => 'شحن مشتريات اختبار',
            'type' => 'expense',
            'level' => 3,
            'parent_id' => $expenseRoot->id,
            'detail_type' => 'freight_in',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->cogsAcc = TreeAccount::create([
            'code' => $p.'6001',
            'name' => 'تكلفة مبيعات اختبار',
            'type' => 'expense',
            'level' => 3,
            'parent_id' => $expenseRoot->id,
            'detail_type' => 'cogs',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->bank = Bank::create([
            'name' => 'بنك اختبار '.$p,
            'type' => 'بنك',
            'usage' => 'test',
            'balance' => 10000,
            'asset_id' => $this->bankAcc->id,
        ]);
    }

    private function seedTransactionTypes(): void
    {
        foreach (['PURCHASE_ADD', 'SALES_RETURN', 'PURCHASE_RETURN', 'AMANAT_RETURN'] as $code) {
            $type = TransactionType::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $code,
                    'prefix' => substr($code, 0, 3),
                    'affects_stock' => true,
                    'stock_direction' => in_array($code, ['PURCHASE_RETURN'], true) ? 'out' : 'in',
                    'is_active' => true,
                ]
            );
            DocumentSequence::query()->firstOrCreate(
                ['transaction_type_id' => $type->id],
                ['prefix' => $type->prefix ?? 'TST', 'last_number' => 9000]
            );
        }
    }

    private function seedSupplierAndCategory(): void
    {
        $this->supplier = Supplier::create([
            'supplier_name' => 'مورد اختبار '.$this->codePrefix,
            'balance' => 0,
            'last_balance' => 0,
            'tree_account_id' => $this->supplierAcc->id,
        ]);

        $stock = Stock::create([
            'name' => 'مخزن خام '.$this->codePrefix,
            'asset_id' => $this->inventoryAcc->id,
        ]);

        $productionId = DB::table('productions')->value('id');
        $measurementId = DB::table('measurements')->value('id');
        $this->assertNotNull($productionId, 'productions table must have at least one row for purchase tests');
        $this->assertNotNull($measurementId, 'measurements table must have at least one row for purchase tests');

        $this->category = Category::create([
            'category_name' => 'صنف خام '.$this->codePrefix,
            'category_price' => 0,
            'unit_price' => 0,
            'total_price' => 0,
            'initial_balance' => 0,
            'minimum_quantity' => 0,
            'warehouse' => 'مخزن مواد خام',
            'stock_id' => $stock->id,
            'production_id' => $productionId,
            'measurement_id' => $measurementId,
        ]);

        DB::table('categories')->where('id', $this->category->id)->update(['quantity' => 0]);
        $this->category->refresh();
    }
}
