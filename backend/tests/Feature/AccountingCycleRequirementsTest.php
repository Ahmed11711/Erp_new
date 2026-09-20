<?php

namespace Tests\Feature;

use App\Models\AccountEntry;
use App\Models\Category;
use App\Models\DailyEntry;
use App\Models\Manufacture;
use App\Models\ManufactureProduct;
use App\Models\Measurement;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Production;
use App\Models\Safe;
use App\Models\ShippingCompany;
use App\Models\shippingCompanyDetails;
use App\Models\Stock;
use App\Models\TreeAccount;
use App\Models\User;
use App\Services\Accounting\AccountLinkingService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * اختبار حقيقي (على قاعدة البيانات الفعلية مع rollback) لمتطلبات قسم الحسابات:
 *
 *  1) عند «تم التسليم»:  من حـ/ المندوب  إلى حـ/ العميل  → كشف العميل صفر، والمديونية على المندوب،
 *     وعند توريد المندوب للخزينة (سند قبض) يُقفل رصيد المندوب.
 *  2) عند أمر التصنيع:    من حـ/ مخزون تحت التشغيل  إلى حـ/ مخزون الخامات.
 *  3) عند اكتمال الإنتاج: من حـ/ مخزون المنتج التام  إلى حـ/ مخزون تحت التشغيل.
 *  4) عند البيع:          من حـ/ تكلفة البضاعة المباعة  إلى حـ/ مخزون المنتج التام (بالتكلفة الفعلية).
 */
class AccountingCycleRequirementsTest extends TestCase
{
    private User $admin;

    private Production $production;

    private Measurement $measurement;

    private Stock $rawStock;

    private Stock $finishedStock;

    private Stock $wipStock;

    private TreeAccount $rawAcc;

    private TreeAccount $wipAcc;

    private TreeAccount $fgAcc;

    private TreeAccount $cogsAcc;

    private TreeAccount $salesAcc;

    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        $this->prefix = 'ACC' . substr(str_replace('.', '', uniqid('', true)), -8);

        $this->admin = User::where('department', 'Admin')->first()
            ?? User::factory()->create(['department' => 'Admin']);

        $this->production = Production::first() ?? Production::create([
            'warehouse' => 'مخزن مواد خام',
            'production_line' => 'test',
        ]);
        $this->measurement = Measurement::first() ?? Measurement::create([
            'unit' => 'قطعة',
            'warehouse' => 'مخزن مواد خام',
        ]);

        $this->rawStock = Stock::where('name', 'مخزن مواد خام')->firstOrFail();
        $this->finishedStock = Stock::where('name', 'مخزن منتج تام')->firstOrFail();
        $this->wipStock = Stock::where('name', 'مخزن منتج تحت التشغيل')->firstOrFail();

        $this->rawAcc = TreeAccount::resolveInventoryAccountForStock($this->rawStock);
        $this->wipAcc = TreeAccount::resolveInventoryAccountForStock($this->wipStock);
        $this->fgAcc = TreeAccount::resolveInventoryAccountForStock($this->finishedStock);
        $this->cogsAcc = TreeAccount::resolveCogsAccount();
        $this->salesAcc = TreeAccount::resolveSalesRevenueAccount();

        $this->assertNotNull($this->rawAcc, 'حساب مخزون الخامات غير مربوط بالمخزن');
        $this->assertNotNull($this->wipAcc, 'حساب مخزون تحت التشغيل غير مربوط بالمخزن');
        $this->assertNotNull($this->fgAcc, 'حساب مخزون المنتج التام غير مربوط بالمخزن');
        $this->assertNotNull($this->cogsAcc, 'حساب تكلفة المبيعات غير موجود');
        $this->assertNotNull($this->salesAcc, 'حساب إيرادات المبيعات غير موجود');
        $this->assertNotSame((int) $this->rawAcc->id, (int) $this->wipAcc->id);
        $this->assertNotSame((int) $this->wipAcc->id, (int) $this->fgAcc->id);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    // ---------------------------------------------------------------------
    // 2 + 3) أمر تصنيع على مرحلتين: خام → تحت التشغيل → منتج تام
    // ---------------------------------------------------------------------

    public function test_manufacturing_in_two_stages_moves_raw_to_wip_then_wip_to_finished(): void
    {
        [$fg, $raw] = $this->makeFinishedWithRawBom(bomQty: 2, unitCost: 10, rawQty: 100);
        $batchQty = 5;
        $expectedRawCost = 2 * 10 * $batchQty; // 100

        // المرحلة الأولى: إصدار أمر التصنيع (في التصنيع) → من حـ/ تحت التشغيل إلى حـ/ الخامات
        $confirm = $this->actingAs($this->admin, 'api')->postJson('/api/manufacture/confirm', [
            'product_id' => $fg->id,
            'quantity' => $batchQty,
            'status' => 'في التصنيع',
            'total' => $expectedRawCost,
            'date' => now()->toDateString(),
        ]);
        $confirm->assertCreated();
        $mfgId = (int) $confirm->json('id');

        $raw->refresh();
        $this->assertEqualsWithDelta(100 - (2 * $batchQty), (float) $raw->quantity, 0.0001, 'كمية الخامات يجب أن تنقص');

        $cons = $this->entriesByBatch('MFG-CONS-' . $mfgId);
        $this->assertCount(2, $cons, 'قيد استهلاك الخامات يجب أن يكون سطرين');
        $this->assertEqualsWithDelta($expectedRawCost, $this->debitOf($cons, $this->wipAcc->id), 0.01, 'مدين مخزون تحت التشغيل');
        $this->assertEqualsWithDelta($expectedRawCost, $this->creditOf($cons, $this->rawAcc->id), 0.01, 'دائن مخزون الخامات');
        $this->assertBalanced($cons);
        $this->assertEqualsWithDelta(0, $this->debitOf($cons, $this->fgAcc->id) + $this->creditOf($cons, $this->fgAcc->id), 0.001, 'المنتج التام لا يتأثر في مرحلة التشغيل');

        // المرحلة الثانية: اكتمال الإنتاج → من حـ/ المنتج التام إلى حـ/ تحت التشغيل
        $fgQtyBefore = (float) $fg->fresh()->quantity;
        $done = $this->actingAs($this->admin, 'api')->getJson('/api/manufacture/done/' . $mfgId);
        $done->assertOk();

        $this->assertEqualsWithDelta($fgQtyBefore + $batchQty, (float) $fg->fresh()->quantity, 0.0001, 'كمية المنتج التام تزيد');

        $doneEntries = $this->entriesByBatch('MFG-DONE-' . $mfgId);
        $this->assertCount(2, $doneEntries, 'قيد إتمام الإنتاج يجب أن يكون سطرين');
        $this->assertEqualsWithDelta($expectedRawCost, $this->debitOf($doneEntries, $this->fgAcc->id), 0.01, 'مدين مخزون المنتج التام');
        $this->assertEqualsWithDelta($expectedRawCost, $this->creditOf($doneEntries, $this->wipAcc->id), 0.01, 'دائن مخزون تحت التشغيل');
        $this->assertBalanced($doneEntries);

        // صافي أثر الدورة: تحت التشغيل صفر، الخامات −100، المنتج التام +100
        $all = $cons->merge($doneEntries);
        $this->assertEqualsWithDelta(0, $this->netOf($all, $this->wipAcc->id), 0.01, 'رصيد تحت التشغيل يُقفل بعد الاكتمال');
        $this->assertEqualsWithDelta(-$expectedRawCost, $this->netOf($all, $this->rawAcc->id), 0.01);
        $this->assertEqualsWithDelta($expectedRawCost, $this->netOf($all, $this->fgAcc->id), 0.01);
        $this->assertEqualsWithDelta(0, $this->netOf($all, $this->cogsAcc->id), 0.001, 'التصنيع لا يلمس تكلفة المبيعات');
    }

    public function test_manufacturing_completed_in_one_step_posts_raw_to_finished_directly(): void
    {
        [$fg, $raw] = $this->makeFinishedWithRawBom(bomQty: 2, unitCost: 10, rawQty: 100);
        $expectedRawCost = 2 * 10 * 3; // 60

        $confirm = $this->actingAs($this->admin, 'api')->postJson('/api/manufacture/confirm', [
            'product_id' => $fg->id,
            'quantity' => 3,
            'status' => 'تم الانتهاء',
            'total' => $expectedRawCost,
            'date' => now()->toDateString(),
        ]);
        $confirm->assertCreated();
        $mfgId = (int) $confirm->json('id');

        $this->assertEqualsWithDelta(94, (float) $raw->fresh()->quantity, 0.0001);
        $this->assertEqualsWithDelta(3, (float) $fg->fresh()->quantity, 0.0001);

        $this->assertCount(0, $this->entriesByBatch('MFG-CONS-' . $mfgId), 'الاكتمال الفوري لا ينشئ قيد WIP منفصل');

        $done = $this->entriesByBatch('MFG-DONE-' . $mfgId);
        $this->assertCount(2, $done);
        $this->assertEqualsWithDelta($expectedRawCost, $this->debitOf($done, $this->fgAcc->id), 0.01, 'مدين مخزون المنتج التام');
        $this->assertEqualsWithDelta($expectedRawCost, $this->creditOf($done, $this->rawAcc->id), 0.01, 'دائن مخزون الخامات');
        $this->assertBalanced($done);
        $this->assertEqualsWithDelta(0, $this->netOf($done, $this->wipAcc->id), 0.001, 'صافي تحت التشغيل صفر (نفس نتيجة المرحلتين)');
    }

    // ---------------------------------------------------------------------
    // 4 + 1) بيع: شحن (إيراد + تكلفة) → تسليم (نقل الذمة للمندوب) → توريد للخزينة
    // ---------------------------------------------------------------------

    public function test_sales_ship_deliver_then_courier_remits_via_receipt_voucher(): void
    {
        $ctx = $this->runShipAndDeliverCycle();
        $order = $ctx['order'];
        $netTotal = $ctx['net_total'];

        // ---------- (ج) توريد المندوب للخزينة — سند قبض ----------
        $voucher = $this->actingAs($this->admin, 'api')->postJson('/api/accounting/vouchers', [
            'date' => now()->toDateString(),
            'type' => 'receipt',
            'voucher_type' => 'shipping_company',
            'account_id' => $ctx['safe_acc']->id,
            'shipping_company_id' => $ctx['courier']->id,
            'amount' => $netTotal,
            'settled_order_ids' => [$order->id],
            'notes' => 'توريد اختبار ' . $this->prefix,
        ]);
        $voucher->assertCreated();
        $voucherId = (int) $voucher->json('data.id');

        $remit = AccountEntry::where('voucher_id', $voucherId)->get();
        $this->assertCount(2, $remit, 'قيد التوريد سطران');
        $this->assertEqualsWithDelta($netTotal, $this->debitOf($remit, $ctx['safe_acc']->id), 0.01, 'مدين الخزينة');
        $this->assertEqualsWithDelta($netTotal, $this->creditOf($remit, $ctx['courier_acc']->id), 0.01, 'دائن المندوب — إقفال رصيده');
        $this->assertBalanced($remit);

        $line = $ctx['courier_line']->fresh();
        $this->assertSame(1, (int) $line->is_done, 'سطر المندوب أُقفل بالتوريد');
        $this->assertSame('تم التحصيل', $line->status);

        $order->refresh()->load('order_details');
        $this->assertSame('تم التحصيل', $order->order_status, 'حالة الطلب تتحول إلى تم التحصيل بعد سند القبض');
        $this->assertEqualsWithDelta(0, (float) $order->order_details->shipping_receivable_amount, 0.001, 'مستحق الشحن المخزَّن صُفِّي');
        $this->assertSame('collected', (string) $order->order_details->collection_status);
        $this->assertNotNull($order->order_details->collection_date);

        $this->assertCycleClosed($ctx, AccountEntry::query()
            ->where(fn ($q) => $q->where('order_id', $order->id)->orWhere('voucher_id', $voucherId))
            ->get());
    }

    public function test_sales_ship_deliver_then_courier_remits_via_order_collection(): void
    {
        $ctx = $this->runShipAndDeliverCycle();
        $order = $ctx['order'];
        $netTotal = $ctx['net_total'];

        // ---------- (ج) توريد المندوب للخزينة — تحصيل الطلب ----------
        $collect = $this->actingAs($this->admin, 'api')->postJson('/api/collectorder/' . $order->id, [
            'payment_type' => 'safe',
            'safe_id' => $ctx['safe']->id,
        ]);
        $collect->assertOk();

        $remit = AccountEntry::where('order_id', $order->id)
            ->whereIn('tree_account_id', [$ctx['safe_acc']->id, $ctx['courier_acc']->id])
            ->where('id', '>', $ctx['last_entry_id'])
            ->get();
        $this->assertEqualsWithDelta($netTotal, $this->debitOf($remit, $ctx['safe_acc']->id), 0.01, 'مدين الخزينة');
        $this->assertEqualsWithDelta($netTotal, $this->creditOf($remit, $ctx['courier_acc']->id), 0.01, 'دائن المندوب — إقفال رصيده');
        $this->assertBalanced($remit);

        $line = $ctx['courier_line']->fresh();
        $this->assertSame(1, (int) $line->is_done, 'سطر المندوب أُقفل بالتحصيل');
        $this->assertSame('تم التحصيل', $line->status);
        $this->assertSame('تم التحصيل', $order->fresh()->order_status, 'حالة الطلب تتحول إلى تم التحصيل');

        $this->assertCycleClosed($ctx, AccountEntry::where('order_id', $order->id)->get());
    }

    /**
     * يشحن ثم يسلّم طلباً حقيقياً عبر HTTP ويتحقق من قيود الشحن (إيراد + تكلفة) وقيد التسليم (مندوب/عميل).
     *
     * @return array<string, mixed>
     */
    private function runShipAndDeliverCycle(): array
    {
        $unitCost = 50.0;
        $qty = 2;
        $unitPrice = 500.0;
        $netTotal = $qty * $unitPrice; // 1000
        $expectedCogs = $qty * $unitCost; // 100

        $fg = $this->makeFinishedGoodsInStock(qty: 10, unitCost: $unitCost, sellPrice: $unitPrice);

        // مندوب مرتبط بحساب ذمم منفصل (أصل) + خزينة مرتبطة بحساب في الشجرة
        $courierRecvAcc = $this->makeAssetAccount('ذمم مندوب ' . $this->prefix, 'CR');
        $courier = ShippingCompany::create([
            'name' => 'مندوب اختبار ' . $this->prefix,
            'type' => 'مندوب',
            'receivable_tree_account_id' => $courierRecvAcc->id,
        ]);

        $safeAcc = $this->makeAssetAccount('خزينة اختبار ' . $this->prefix, 'SF');
        $safe = Safe::create([
            'name' => 'خزينة اختبار ' . $this->prefix,
            'balance' => 0,
            'type' => 'main',
            'account_id' => $safeAcc->id,
        ]);

        // مصدر طلب غير أونلاين حتى يُفتح للعميل حساب فردي مستقل (وليس حساب قناة أونلاين مجمّع)
        $orderSourceId = (int) DB::table('order_sources')->insertGetId([
            'name' => 'مصدر اختبار ' . $this->prefix,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $phone = '011' . substr((string) random_int(10000000, 99999999), 0, 8);
        $customerAcc = app(AccountLinkingService::class)
            ->resolveOrderCustomerAccount('افراد', 'عميل اختبار ' . $this->prefix, $phone, null, $orderSourceId);
        $this->assertNotNull($customerAcc, 'تعذر إنشاء حساب العميل');
        $this->assertEqualsWithDelta(0, $this->treeBalance($customerAcc->id), 0.001, 'حساب العميل جديد ورصيده صفر');

        $order = Order::withoutEvents(fn () => Order::create([
            'customer_name' => 'عميل اختبار ' . $this->prefix,
            'customer_type' => 'افراد',
            'customer_phone_1' => $phone,
            'customer_phone_2' => '-',
            'tel' => '-',
            'governorate' => 'القاهرة',
            'city' => 'القاهرة',
            'address' => 'عنوان اختبار',
            'order_date' => now()->toDateString(),
            'shipping_method_id' => DB::table('shipping_methods')->value('id') ?? 1,
            'order_source_id' => $orderSourceId,
            'order_type' => 'جديد',
            'shipping_cost' => 0,
            'shipping_revenue' => 0,
            'total_invoice' => $netTotal,
            'prepaid_amount' => 0,
            'discount' => 0,
            'net_total' => $netTotal,
            'order_status' => 'طلب مؤكد',
        ]));
        $op = OrderProduct::create([
            'order_id' => $order->id,
            'category_id' => $fg->id,
            'quantity' => $qty,
            'price' => $unitPrice,
            'total_price' => $netTotal,
        ]);

        // ---------- (أ) تم الشحن — استلام المندوب للطلب ----------
        $ship = $this->actingAs($this->admin, 'api')->post('/api/shiporder/' . $order->id, [
            'date' => now()->toDateString(),
            'company_id' => $courier->id,
            'productsToShip' => json_encode([['id' => $op->id, 'quantity' => $qty]]),
        ]);
        $ship->assertOk();
        $this->assertSame('تم شحن', $order->fresh()->order_status);
        $this->assertEqualsWithDelta(8, (float) $fg->fresh()->quantity, 0.0001, 'كمية المنتج التام تنقص عند الشحن');

        // قيد إثبات المبيعات: من حـ/ العميل إلى حـ/ المبيعات
        $invoice = $this->entriesByBatchPrefix('ORD-' . $order->id . '-');
        $this->assertNotEmpty($invoice, 'قيد إثبات المبيعات مفقود');
        $this->assertEqualsWithDelta($netTotal, $this->debitOf($invoice, $customerAcc->id), 0.01, 'مدين العميل بقيمة الفاتورة');
        $this->assertEqualsWithDelta($netTotal, $this->creditOf($invoice, $this->salesAcc->id), 0.01, 'دائن إيرادات المبيعات');
        $this->assertBalanced($invoice);

        // (4) قيد تكلفة البضاعة المباعة: من حـ/ تكلفة المبيعات إلى حـ/ مخزون المنتج التام بالتكلفة الفعلية
        $cogs = $this->entriesByBatchPrefix('COGS-' . $order->id . '-');
        $this->assertCount(2, $cogs, 'قيد التكلفة يجب أن يُرحَّل مرة واحدة بسطرين');
        $this->assertEqualsWithDelta($expectedCogs, $this->debitOf($cogs, $this->cogsAcc->id), 0.01, 'مدين تكلفة البضاعة المباعة = الكمية × التكلفة الفعلية');
        $this->assertEqualsWithDelta($expectedCogs, $this->creditOf($cogs, $this->fgAcc->id), 0.01, 'دائن مخزون المنتج التام بنفس القيمة');
        $this->assertBalanced($cogs);

        // عند الشحن فقط: المديونية ما زالت على العميل، ولا شيء على المندوب بعد
        $this->assertEqualsWithDelta($netTotal, $this->accountNetForOrder($customerAcc->id, $order->id), 0.01, 'بعد الشحن وقبل التسليم: العميل مدين');
        $this->assertEqualsWithDelta(0, $this->accountNetForOrder($courierRecvAcc->id, $order->id), 0.01, 'لا ذمة على المندوب قبل التسليم');
        $this->assertEmpty($this->entriesByBatchPrefix('DELIVERY-' . $order->id . '-'), 'لا يوجد قيد تسليم قبل التسليم');

        // ---------- (ب) تم التسليم — تسليم الطلب للعميل ----------
        $deliver = $this->actingAs($this->admin, 'api')->postJson('/api/order/' . $order->id . '/deliver', []);
        $deliver->assertOk();
        $this->assertSame('تم التسليم', $order->fresh()->order_status);

        // (1) قيد نقل الذمة: من حـ/ المندوب إلى حـ/ العميل
        $delivery = $this->entriesByBatchPrefix('DELIVERY-' . $order->id . '-');
        $this->assertCount(2, $delivery, 'قيد التسليم يجب أن يكون سطرين');
        $this->assertEqualsWithDelta($netTotal, $this->debitOf($delivery, $courierRecvAcc->id), 0.01, 'مدين حساب المندوب بقيمة الطلب');
        $this->assertEqualsWithDelta($netTotal, $this->creditOf($delivery, $customerAcc->id), 0.01, 'دائن العميل بقيمة الطلب');
        $this->assertBalanced($delivery);

        // كشف حساب العميل بعد التسليم = صفر، والمديونية انتقلت للمندوب
        $this->assertEqualsWithDelta(0, $this->accountNetForOrder($customerAcc->id, $order->id), 0.01, 'كشف حساب العميل لا يُظهر مديونية بعد التسليم');
        $this->assertEqualsWithDelta($netTotal, $this->accountNetForOrder($courierRecvAcc->id, $order->id), 0.01, 'المديونية على المندوب');
        $this->assertEqualsWithDelta(0, $this->treeBalance($customerAcc->id), 0.01, 'رصيد شجرة الحسابات للعميل صفر');
        $this->assertEqualsWithDelta($netTotal, $this->treeBalance($courierRecvAcc->id), 0.01, 'رصيد شجرة الحسابات للمندوب = قيمة الطلب');

        // التكلفة لم تتكرر عند التسليم
        $this->assertCount(2, $this->entriesByBatchPrefix('COGS-' . $order->id . '-'), 'التسليم لا يكرر قيد التكلفة');

        // سطر المستحق التشغيلي على المندوب
        $line = shippingCompanyDetails::where('order_id', $order->id)->where('shipping_company_id', $courier->id)->first();
        $this->assertNotNull($line, 'سطر مستحقات المندوب مفقود');
        $this->assertSame('تم التسليم', $line->status);
        $this->assertSame(0, (int) $line->is_done);
        $this->assertEqualsWithDelta($netTotal, (float) $line->amount, 0.01);

        return [
            'order' => $order,
            'net_total' => $netTotal,
            'expected_cogs' => $expectedCogs,
            'customer_acc' => $customerAcc,
            'courier' => $courier,
            'courier_acc' => $courierRecvAcc,
            'courier_line' => $line,
            'safe' => $safe,
            'safe_acc' => $safeAcc,
            'last_entry_id' => (int) AccountEntry::max('id'),
        ];
    }

    /**
     * بعد التوريد: المندوب والعميل صفر، الخزينة +الإجمالي، وميزان المراجعة وقائمة الدخل متوازنان.
     *
     * @param  array<string, mixed>  $ctx
     */
    private function assertCycleClosed(array $ctx, $cycle): void
    {
        $netTotal = $ctx['net_total'];
        $expectedCogs = $ctx['expected_cogs'];
        $customerAcc = $ctx['customer_acc'];
        $courierRecvAcc = $ctx['courier_acc'];
        $safeAcc = $ctx['safe_acc'];

        $this->assertEqualsWithDelta(0, $this->treeBalance($courierRecvAcc->id), 0.01, 'رصيد المندوب صفر بعد التوريد');
        $this->assertEqualsWithDelta(0, $this->treeBalance($customerAcc->id), 0.01, 'رصيد العميل يبقى صفر');
        $this->assertEqualsWithDelta($netTotal, $this->treeBalance($safeAcc->id), 0.01, 'الخزينة زادت بقيمة التوريد');

        // ---------- ميزان المراجعة لكل قيود الدورة ----------
        $this->assertBalanced($cycle, 'مجموع مدين الدورة = مجموع دائنها');

        $this->assertEqualsWithDelta(0, $this->netOf($cycle, $customerAcc->id), 0.01, 'العميل: صفر');
        $this->assertEqualsWithDelta(0, $this->netOf($cycle, $courierRecvAcc->id), 0.01, 'المندوب: صفر');
        $this->assertEqualsWithDelta($netTotal, $this->netOf($cycle, $safeAcc->id), 0.01, 'الخزينة: +1000');
        $this->assertEqualsWithDelta(-$netTotal, $this->netOf($cycle, $this->salesAcc->id), 0.01, 'المبيعات: دائن 1000');
        $this->assertEqualsWithDelta($expectedCogs, $this->netOf($cycle, $this->cogsAcc->id), 0.01, 'تكلفة المبيعات: مدين 100');
        $this->assertEqualsWithDelta(-$expectedCogs, $this->netOf($cycle, $this->fgAcc->id), 0.01, 'مخزون المنتج التام: دائن 100');

        // قائمة الدخل من الدورة: 1000 − 100 = 900 = صافي حركة الأصول (خزينة +1000 − مخزون 100)
        $grossProfit = -$this->netOf($cycle, $this->salesAcc->id) - $this->netOf($cycle, $this->cogsAcc->id);
        $assetsChange = $this->netOf($cycle, $safeAcc->id) + $this->netOf($cycle, $this->fgAcc->id)
            + $this->netOf($cycle, $customerAcc->id) + $this->netOf($cycle, $courierRecvAcc->id);
        $this->assertEqualsWithDelta($netTotal - $expectedCogs, $grossProfit, 0.01);
        $this->assertEqualsWithDelta($grossProfit, $assetsChange, 0.01, 'مجمل الربح = صافي التغير في الأصول');

        // الدفاتر اليومية المرتبطة بالدورة كلها متوازنة
        $dailyIds = $cycle->pluck('daily_entry_id')->filter()->unique();
        $this->assertGreaterThanOrEqual(4, $dailyIds->count(), 'أربعة قيود على الأقل: فاتورة، تكلفة، تسليم، توريد');
        foreach ($dailyIds as $dailyId) {
            $items = DB::table('daily_entry_items')->where('daily_entry_id', $dailyId)->get();
            $this->assertEqualsWithDelta(
                (float) $items->sum('debit'),
                (float) $items->sum('credit'),
                0.01,
                'قيد يومي غير متوازن #' . $dailyId . ' — ' . DailyEntry::find($dailyId)?->description
            );
        }
    }

    // ---------------------------------------------------------------------
    // helpers
    // ---------------------------------------------------------------------

    /**
     * @return array{0: Category, 1: Category}
     */
    private function makeFinishedWithRawBom(float $bomQty, float $unitCost, float $rawQty): array
    {
        $fg = Category::create([
            'category_name' => 'FG_' . $this->prefix . '_' . uniqid(),
            'category_price' => 100,
            'unit_price' => $bomQty * $unitCost,
            'initial_balance' => 0,
            'minimum_quantity' => 0,
            'warehouse' => $this->finishedStock->name,
            'product_type' => 'finished',
            'production_id' => $this->production->id,
            'measurement_id' => $this->measurement->id,
            'stock_id' => $this->finishedStock->id,
            'category_image' => '',
            'total_price' => 0,
            'sell_total_price' => 0,
        ]);

        $raw = Category::create([
            'category_name' => 'RM_' . $this->prefix . '_' . uniqid(),
            'category_price' => $unitCost,
            'unit_price' => $unitCost,
            'initial_balance' => $rawQty,
            'minimum_quantity' => 0,
            'warehouse' => $this->rawStock->name,
            'product_type' => 'raw_material',
            'production_id' => $this->production->id,
            'measurement_id' => $this->measurement->id,
            'stock_id' => $this->rawStock->id,
            'category_image' => '',
            'total_price' => $rawQty * $unitCost,
        ]);
        // quantity ليست ضمن fillable — تُضبط مباشرة
        $raw->quantity = $rawQty;
        $raw->save();

        $manufacture = Manufacture::create([
            'product_id' => $fg->id,
            'total' => $bomQty * $unitCost,
        ]);
        ManufactureProduct::create([
            'manufacture_id' => $manufacture->id,
            'product_id' => $raw->id,
            'quantity' => $bomQty,
            'total_price' => $bomQty * $unitCost,
        ]);

        return [$fg, $raw];
    }

    private function makeFinishedGoodsInStock(float $qty, float $unitCost, float $sellPrice): Category
    {
        $fg = Category::create([
            'category_name' => 'FG_SALE_' . $this->prefix,
            'category_price' => $sellPrice,
            'unit_price' => $unitCost,
            'initial_balance' => $qty,
            'minimum_quantity' => 0,
            'warehouse' => $this->finishedStock->name,
            'product_type' => 'finished',
            'production_id' => $this->production->id,
            'measurement_id' => $this->measurement->id,
            'stock_id' => $this->finishedStock->id,
            'category_image' => '',
            'total_price' => $qty * $unitCost,
            'sell_total_price' => 0,
        ]);
        $fg->quantity = $qty;
        $fg->save();

        return $fg->fresh();
    }

    private function makeAssetAccount(string $name, string $suffix): TreeAccount
    {
        return TreeAccount::create([
            'code' => $this->prefix . $suffix,
            'name' => $name,
            'type' => 'asset',
            'level' => 2,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);
    }

    private function entriesByBatch(string $batch)
    {
        return AccountEntry::where('entry_batch_code', $batch)->get();
    }

    private function entriesByBatchPrefix(string $prefix)
    {
        return AccountEntry::where('entry_batch_code', 'like', $prefix . '%')->get();
    }

    private function debitOf($entries, int $accountId): float
    {
        return round((float) $entries->where('tree_account_id', $accountId)->sum('debit'), 2);
    }

    private function creditOf($entries, int $accountId): float
    {
        return round((float) $entries->where('tree_account_id', $accountId)->sum('credit'), 2);
    }

    private function netOf($entries, int $accountId): float
    {
        return round($this->debitOf($entries, $accountId) - $this->creditOf($entries, $accountId), 2);
    }

    private function accountNetForOrder(int $accountId, int $orderId): float
    {
        $rows = AccountEntry::where('tree_account_id', $accountId)->where('order_id', $orderId)->get();

        return round((float) $rows->sum('debit') - (float) $rows->sum('credit'), 2);
    }

    private function treeBalance(int $accountId): float
    {
        $rows = AccountEntry::where('tree_account_id', $accountId)->get();

        return round((float) $rows->sum('debit') - (float) $rows->sum('credit'), 2);
    }

    private function assertBalanced($entries, string $message = 'القيد غير متوازن'): void
    {
        $this->assertEqualsWithDelta(
            round((float) $entries->sum('debit'), 2),
            round((float) $entries->sum('credit'), 2),
            0.01,
            $message
        );
    }
}
