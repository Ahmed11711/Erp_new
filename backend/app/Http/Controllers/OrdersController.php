<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Bank;
use App\Models\Note;
use App\Models\User;
use App\Models\Order;
use App\Models\Category;
use App\Models\Item;
use App\Models\tracking;
use App\Models\Notification;
use App\Models\OrderDetails;
use App\Models\OrderProduct;
use Illuminate\Http\Request;
use App\Filters\OrderFilters;
use App\Models\customerCompany;
use App\Models\OrderTempReview;
use App\Models\ShippingCompany;
use App\Models\CollectionCompany;
use App\Enums\CollectionProviderType;
use App\Enums\OrderCollectionStatus;
use App\Enums\OrderSettlementStatus;
use App\Enums\OrderDeliveryStatus;
use App\Services\Accounting\SalesOrderAccountingService;
use App\Services\WhatsAppService;
use App\Models\OrderMaintenReason;
use App\Models\PendingBankBalance;
use Illuminate\Support\Facades\DB;
use App\Models\OrderProductArchive;
use App\Models\OrderShippingNumber;
use Illuminate\Support\Facades\Log;
use Twilio\TwiML\MessagingResponse;
use Illuminate\Support\Facades\Cache;
use App\Models\shippingCompanyDetails;
use App\Services\Accounting\InventoryGlPostingService;
use App\Services\Accounting\AccountLinkingService;
use App\Services\Accounting\LedgerJournalService;
use App\Services\Accounting\ShippingCourierAccountingService;
use App\Services\CategoryInventoryCostService;
use App\Enums\InventoryMovementType;
use App\Services\Inventory\InventoryMovementLedgerService;
use App\Services\Shipping\CollectionReceivableAccountResolver;
use App\Services\Shipping\OrderFinancialStateService;
use App\Services\Shipping\ShipmentReceivableAccountGuard;
use App\Services\Shipping\ShippingReceivableSplitService;
use App\Services\Shipping\UnlinkedReceivableAccountException;
use App\Support\CollectionProviderMorph;
use App\Services\Orders\OrderStatusVisibilityService;
use App\Support\RbacLegacyAccess;
use App\Services\Accounting\DeliveryConfirmationAccountingService;
use App\Services\Orders\OrderCancellationAccountingService;
use App\Services\Orders\OrderEditAccountingService;
use App\Services\Orders\OrderEditApplyService;
use App\Services\Orders\OrderLineCancellationService;
use App\Services\Orders\OrderPrepaidAdjustmentService;
use App\Services\Shopify\ShopifyOrderReviewApplyService;

class OrdersController extends Controller
{
    public function __construct(
        protected OrderStatusVisibilityService $orderStatusVisibility,
    ) {
    }

    /** حالات سطر شركة الشحن التي ما زالت مفتوحة للتحصيل (قبل `تم التحصيل`). */
    private const SHIPPING_DETAIL_OPEN_COLLECTION_STATUSES = ['تم شحن', 'تم التسليم'];

    /** حالات الطلب التي لا يُسمح فيها برفض الاستلام. */
    private const ORDER_REFUSE_BLOCKED_STATUSES = ['رفض استلام', 'تم التحصيل', 'ملغي', 'أرشيف'];

    /** حالات الطلب المشحونة/المسلَّمة التي يُسمح فيها تحصيل متغير. */
    private const VARIABLE_COLLECTION_ORDER_STATUSES = ['تم شحن', 'تم التسليم'];

    /** أنواع الطلبات التي تُنقص المخزون عبر category_procedure وتُثبت COGS/الإيراد كمسار «جديد». */
    private const ORDER_TYPES_INVENTORY_SHIP = ['جديد', 'طلب استبدال'];

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function rejectIfOrderContainsBlockedSemiFinished(array $rows): ?\Illuminate\Http\JsonResponse
    {
        foreach ($rows as $od) {
            $lineItem = Item::query()->find((int) ($od['category_id'] ?? 0));
            if ($lineItem && ! $lineItem->canSellOnSalesChannel()) {
                return response()->json([
                    'message' => 'لا يمكن بيع صنف تحت التشغيل (WIP) في قناة المبيعات ما لم يُفعّل خيار السماح بالبيع لهذا الصنف: '
                        . $lineItem->category_name,
                ], 422);
            }
        }

        return null;
    }

    private function orderProfileAllows(string $profile): bool
    {
        $profiles = config('order_rbac_profiles', []);
        if (! isset($profiles[$profile])) {
            return false;
        }

        $cfg = $profiles[$profile];

        return RbacLegacyAccess::passes(
            auth()->user(),
            $cfg['departments'] ?? [],
            $cfg['permissions'] ?? []
        );
    }

    private function orderAllowsRefuseReceipt(Order $order): bool
    {
        return ! in_array((string) $order->order_status, self::ORDER_REFUSE_BLOCKED_STATUSES, true);
    }

    private function isVariableCollectionOrderStatus(string $orderStatus): bool
    {
        return in_array($orderStatus, self::VARIABLE_COLLECTION_ORDER_STATUSES, true);
    }

    public function index()
    {
        $itemsPerPage = request('itemsPerPage') ?: 10;

        $orders = Order::with('order_details.shipping_line', 'order_details.shipping_company', 'shipping_method', 'order_products.category:id,category_name')
            ->visibleToUser(auth()->user())
            ->orderBy('id', 'desc')
            ->paginate($itemsPerPage);

        return response()->json($orders, 200);
    }

    public function visibleStatuses()
    {
        $user = auth()->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'statuses' => $this->orderStatusVisibility->filterOptionsFor($user),
            'labels' => $this->orderStatusVisibility->visibleStatusLabels($user),
        ], 200);
    }

    public function getTrackings()
    {
        $itemsPerPage = (int) (request('itemsPerPage') ?: 15);

        $tracking = tracking::with([
            'order:id,order_status',
            'user:id,name',
        ]);

        $createdAt = trim((string) request('created_at', ''));
        if ($createdAt !== '' && $createdAt !== '0') {
            $tracking->whereDate('created_at', $createdAt);
        }

        $userId = (int) request('user_id', 0);
        if ($userId > 0) {
            $tracking->where('user_id', $userId);
        }

        if (request()->filled('action')) {
            $tracking->where('action', 'like', '%' . request('action') . '%');
        }

        $tracking = $tracking->orderBy('id', 'desc')
            ->paginate($itemsPerPage);

        $tracking->getCollection()->transform(function (tracking $row) {
            $row->type = $row->type ?? $row->action;
            $row->order_status = $row->order_status ?? $row->order?->order_status;
            $row->is_undo = (int) ($row->is_undo ?? 0);

            return $row;
        });

        return response()->json($tracking, 200);
    }

    public function undo(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:trackings,id',
        ]);

        $row = tracking::with('order')->findOrFail((int) $request->id);
        $order = $row->order;

        if (! $order) {
            return response()->json(['message' => 'الطلب المرتبط بالتتبع غير موجود.'], 422);
        }

        $previous = tracking::query()
            ->where('order_id', $row->order_id)
            ->where('id', '<', $row->id)
            ->orderByDesc('id')
            ->value('action');

        if (! is_string($previous) || trim($previous) === '') {
            return response()->json(['message' => 'لا توجد حالة سابقة للتراجع إليها.'], 422);
        }

        if ((string) $order->order_status !== (string) ($row->action ?? '')) {
            return response()->json(['message' => 'تغيّرت حالة الطلب — لا يمكن التراجع عن هذا السجل.'], 422);
        }

        DB::transaction(function () use ($order, $previous, $row) {
            $order->order_status = $previous;
            $order->save();

            OrderDetails::query()
                ->where('order_id', $order->id)
                ->update(['status_date' => now()->toDateString()]);

            DB::table('trackings')->insert([
                'order_id' => $order->id,
                'action' => 'تراجع — ' . ($row->action ?? '—'),
                'date' => now()->toDateString(),
                'user_id' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return response()->json(['message' => 'success'], 200);
    }


    public function getActions()
    {
        $actions = tracking::select('action')
            ->distinct()
            ->orderBy('action', 'asc')
            ->pluck('action');

        return response()->json($actions, 200);
    }



    public function show($id)
    {
        Order::reconcileCollectionStatusIfAllShippingLinesClosed((int) $id);

        $order = Order::with([
            'shipping_method',
            'order_source',
            'shopifyReviewer:id,name',
            'order_details.shipping_line',
            'order_details.shipping_company',
            'bank',
            'maintenReason',
            'order_products.category',
            'order_products_archive.category',
            'order_shipment_number.user',
            'traking.user',
            'note' => function ($query) {
                $query->with([
                    'user:id,name',
                    'editedBy:id,name',
                ])->orderByDesc('created_at');
            },
            'tempReview' => function ($query) {
                $query->whereHas('user', function ($query) {
                    if (auth()->user()->department !== 'Admin') {
                        $query->where('user_id', auth()->user()->id);
                    }
                })->with('user');
            },
            'notifications' => function ($query) {
                if (auth()->user()->department !== 'Admin') {
                    $query->where('send_to', auth()->user()->id);
                }
                $query->with('receiver', 'sender');
            },
        ])->find($id);

        if (!$order) {
            return response()->json(['message' => 'غير موجود'], 404);
        }

        if (! $this->orderStatusVisibility->canViewStatus(auth()->user(), (string) $order->order_status)) {
            return response()->json(['message' => 'غير مصرح بعرض هذا الطلب'], 403);
        }

        $order->tempReviewNotification = $order->notifications()
            ->where('type', 'مراجعة مؤقتة')
            ->where('review_status', '1')
            ->leftJoin('users', 'notifications.send_from', '=', 'users.id')
            ->where('users.department', '!=', 'Admin')
            ->select('notifications.*')
            ->get();

        $authUser = auth()->user();
        $order->note->each(function ($note) use ($authUser) {
            $note->can_edit = $authUser->department === 'Admin'
                || (int) $note->user_id === (int) $authUser->id;
        });

        $prepaidAdjustment = app(OrderPrepaidAdjustmentService::class);
        $canPerm = $this->userCanManageOrderPrepaid($authUser);
        $canAdjust = $prepaidAdjustment->canAdjust($order);
        $order->can_adjust_prepaid = $canPerm && $canAdjust;
        $order->prepaid_adjust_block_reason = ($canPerm && ! $canAdjust && (float) ($order->prepaid_amount ?? 0) > 0.009)
            ? $prepaidAdjustment->ineligibilityReason($order)
            : null;

        return response()->json($order, 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_name' => 'required|string',
            'customer_type' => 'required|string',
            'customer_phone_1' => 'required|string',
            'customer_phone_2' => 'string',
            'tel' => 'string',
            'governorate' => 'required|string',
            'address' => 'required|string',
            'order_date' => 'required|date',
            'shipping_method_id' => 'required|numeric|exists:shipping_methods,id',
            'order_source_id' => 'required|numeric|exists:order_sources,id',
            'order_image' => 'image|mimes:jpeg,png,jpg,gif,svg|max:500',
            'order_type' => 'required|string',
            'shipping_cost' => 'required|numeric',
            'total_invoice' => 'required|numeric',
            'prepaid_amount' => 'required|numeric',
            'discount' => 'required|numeric',
            'net_total' => 'required|numeric',
            'order_details' => 'required',
            'order_details.*.category_id' => 'required|numeric|exists:categories,id',
            'order_details.*.quantity' => 'required|numeric',
            'order_details.*.price' => 'required|numeric',
            'order_details.*.total' => 'required|numeric',
        ]);

        DB::beginTransaction();
        try {
            $img_name = '';
            if ($request->hasFile('order_image')) {
                $img = $request->file('order_image');
                $img_name = time() . '.' . $img->extension();
                $img->move(public_path('images'), $img_name);
            }

            $prepaidMeta = $this->resolvePrepaidPaymentFromRequest($request);
            $bank_id = $prepaidMeta['bank_id'];

            $user_id = auth()->user()->id;

            $order = Order::create([
                'customer_name' => $request->customer_name,
                'customer_type' => $request->customer_type,
                'customer_phone_1' => $request->customer_phone_1,
                'customer_phone_2' => $request->customer_phone_2,
                'tel' => $request->tel,
                'governorate' => $request->governorate,
                'city' => $request->city,
                'address' => $request->address,
                'order_date' => $request->order_date,
                'shipping_method_id' => $request->shipping_method_id,
                'order_source_id' => $request->order_source_id,
                'order_image' => $img_name,
                'order_type' => $request->order_type,
                'shipping_cost' => $request->shipping_cost,
                'shipping_revenue' => $request->input('shipping_revenue', $request->shipping_cost),
                'courier_shipping_cost' => $request->input('courier_shipping_cost'),
                'total_invoice' => $request->total_invoice,
                'prepaid_amount' => $request->prepaid_amount,
                'discount' => $request->discount,
                'net_total' => $request->net_total,
                'bank_id' => $bank_id,
                'prepaid_payment_type' => $prepaidMeta['type'],
                'vat' => $request->vat,
                'sales'=>$request->Sales ?? 0,
                'company_id' => $request->company_id,
                'delivery_date' => $request->delivery_date === 'null' ? null : $request->delivery_date,
            ]);

            if ($request->private_order) {
                $order->private_order = $request->private_order;
                $order->save();
            }

            $order_products = $request->order_details;
            $order_products = json_decode($order_products, true);

            if ($blocked = $this->rejectIfOrderContainsBlockedSemiFinished($order_products)) {
                DB::rollBack();

                return $blocked;
            }

            $insertData = [];
            foreach ($order_products as $od) {
                $insertData[] = [
                    'order_id' => $order->id,
                    'category_id' => $od['category_id'],
                    'quantity' => $od['quantity'],
                    'price' => $od['price'],
                    'total_price' => $od['total'],
                    'special_details' => $od['special_details'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                // OrderProduct::create([
                //     'order_id' => $order->id,
                //     'category_id' => $od['category_id'],
                //     'quantity' => $od['quantity'],
                //     'price' => $od['price'],
                //     'total_price' => $od['total'],
                // ]);
            }
            OrderProduct::insert($insertData);

            $order_details = OrderDetails::updateOrCreate(
                ['order_id' => $order->id]
            );

            if ($request->order_type == 'طلب صيانة') {
                $order_details->maintenance_cost = $request->maintenance_cost;
                $order_details->save();

                OrderMaintenReason::create([
                    'order_id' => $order->id,
                    'order_status' => 'طلب جديد',
                    'mainten_reason' => $request->maintenReason,
                ]);
            }

            $action = 'طلب جديد';
            $this->insertTracking($order->id, $action, $user_id, now());

            if ($request->customer_type == 'شركة' && $request->has('company_id')) {
                DB::table('customer_companies')->where('id', $request->company_id)->increment('number_of_orders', 1);

                if ($order->customer_type == 'شركة' && $request->has('prepaid_amount') && $request->prepaid_amount != '' && $request->prepaid_amount != 0) {
                    $action = $prepaidMeta['type'] === 'pending'
                        ? ' مبلغ تحت الحساب ' . $request->prepaid_amount . ' — بانتظار تسجيل مصدر الدفع'
                        : ' مبلغ تحت الحساب ' . $request->prepaid_amount . ' في حساب ' . ($prepaidMeta['source_label'] ?? '—');
                    $this->insertTracking($order->id, $action, $user_id, now());

                    if ($prepaidMeta['type'] !== 'pending') {
                        $company_id = $order->company_id;
                        $amount = (float) -$request->prepaid_amount;
                        $ref = $order->id;
                        $details = 'مبلغ تحت الحساب من طلب رقم ' . $order->id;
                        $type = 'الطلبات';
                        DB::statement('CALL update_customer_company_balance(?, ?, ?, ?, ?, ?, ?, ?)', [
                            $company_id,
                            $amount,
                            $bank_id,
                            $ref,
                            $details,
                            $type,
                            $user_id,
                            now()
                        ]);
                    }
                }
            }

            if ($request->has('order_notes') && $request->order_notes != '') {
                $note = $request->input('order_notes');
                $added_from = 'الاضافة';
            $this->insertNote($order->id, $user_id, $note, $added_from, now());
            }

            if ($request->has('prepaid_amount') && $request->prepaid_amount != '' && $request->prepaid_amount != 0) {
                if ($prepaidMeta['type'] === 'pending') {
                    $this->insertTracking(
                        $order->id,
                        'مبلغ تحت الحساب ' . $request->prepaid_amount . ' — بانتظار تسجيل مصدر الدفع (بنك/خزينة/حساب خدمي)',
                        $user_id,
                        now()
                    );
                } else {
                    $amount = (float) $request->prepaid_amount;
                    $details = 'مبلغ تحت الحساب';
                    $ref = $order->id;
                    $type = 'الطلبات';
                    $paymentType = $prepaidMeta['type'];

                    // Update cash/bank/safe operational balance + GL (Dr source / Cr receivable).
                    if ($paymentType === 'safe' && $request->filled('safe_id')) {
                        $this->updateSafeBalance($request->safe_id, $amount, $order->id, $user_id, $details, $ref, $type, now());
                    } elseif ($paymentType === 'service_account' && $request->filled('service_account_id')) {
                        $this->updateServiceAccountBalance($request->service_account_id, $amount, $order->id, $user_id, $details, $ref, $type, now());
                    } elseif ($paymentType === 'bank' && $prepaidMeta['bank_id']) {
                        $this->updateBankBalance($prepaidMeta['bank_id'], $amount, $order->id, $user_id, $details, $ref, $type, now());
                    }

                    if ($order->customer_type !== 'شركة') {
                        $this->insertTracking(
                            $order->id,
                            'مبلغ تحت الحساب ' . $request->prepaid_amount . ' في ' . ($prepaidMeta['source_label'] ?? 'مصدر الدفع'),
                            $user_id,
                            now()
                        );
                    }
                }
            }

            DB::commit();
            return response()->json(['message' => 'success'], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function edit($id, Request $request, OrderEditApplyService $orderEditApplyService, OrderEditAccountingService $orderEditAccountingService)
    {
        DB::beginTransaction();
        try {
            $img_name = '';
            if ($request->hasFile('order_image')) {
                $img = $request->file('order_image');
                $img_name = time() . '.' . $img->extension();
                $img->move(public_path('images'), $img_name);
            }

            $bank_id = null;
            $order = Order::query()
                ->with(['order_products.category', 'shipping_method'])
                ->where('id', $id)
                ->first();

            if (! $order) {
                return response()->json(['message' => 'not found'], 404);
            }

            $accountingBefore = $orderEditAccountingService->snapshotBeforeEdit($order);

            $canEditShipped = RbacLegacyAccess::passes(auth()->user(), [], ['orders.edit']);
            if (
                ! (in_array($order->order_type, ['جديد', 'طلب استبدال', 'طلب مرتجع'], true)
                    && in_array($order->order_status, ['طلب جديد', 'طلب مؤكد', 'شحن جزئي'], true))
                && ! ($this->isVariableCollectionOrderStatus((string) $order->order_status) && $canEditShipped)
            ) {
                return response()->json([
                    'message' => ' حالة الطلب الحاليه ' . $order->order_status . ' ونوع الطلب ' . $order->order_type . ' ولا يمكنك التعديل ',
                ], 422);
            }

            $orderStatus = $order->order_status;
            $orderID = $order->id;
            $editorName = (string) auth()->user()->name;

            if ($order->bank_id) {
                $bank_id = $order->bank_id;
            }
            if ($request->prepaid_amount != 0) {
                $bank_id = $request->bank_id;
            }
            if ($bank_id === 'null') {
                $bank_id = null;
            }

            $order_details = json_decode((string) $request->order_details, true);
            if (! is_array($order_details)) {
                return response()->json(['message' => 'بيانات المنتجات غير صالحة'], 422);
            }

            if ($blocked = $this->rejectIfOrderContainsBlockedSemiFinished($order_details)) {
                return $blocked;
            }

            $headerInput = [];
            foreach ([
                'customer_name', 'customer_phone_1', 'customer_phone_2', 'tel',
                'governorate', 'city', 'address', 'customer_type', 'company_id',
                'shipping_method_id', 'shipping_cost', 'shipping_revenue', 'courier_shipping_cost',
                'total_invoice', 'prepaid_amount', 'discount', 'net_total', 'vat',
            ] as $field) {
                if ($request->has($field)) {
                    $headerInput[$field] = $request->input($field);
                }
            }
            if (! array_key_exists('shipping_revenue', $headerInput) && $request->has('shipping_cost')) {
                $headerInput['shipping_revenue'] = $request->shipping_cost;
            }
            $headerInput['bank_id'] = $bank_id;
            $editPrepaidMeta = $this->resolvePrepaidPaymentFromRequest($request);
            $effectivePrepaid = (float) ($headerInput['prepaid_amount'] ?? $order->prepaid_amount ?? 0);
            if ($effectivePrepaid > 0.0001) {
                $headerInput['prepaid_payment_type'] = $editPrepaidMeta['type'] === 'none'
                    ? ($order->prepaid_payment_type ?? 'pending')
                    : $editPrepaidMeta['type'];
                if ($editPrepaidMeta['bank_id']) {
                    $headerInput['bank_id'] = $editPrepaidMeta['bank_id'];
                }
            } else {
                $headerInput['prepaid_payment_type'] = null;
            }
            if ($request->filled('changed_collect_note')) {
                $headerInput['collect_note'] = $request->input('changed_collect_note');
            }
            if ($img_name !== '') {
                $headerInput['order_image'] = $img_name;
            }

            $headerPayload = $orderEditApplyService->filterHeaderPayload($headerInput);

            $auditChanges = array_merge(
                $orderEditApplyService->collectOrderFieldChanges($order, $headerPayload),
                $orderEditApplyService->collectProductChanges($order->order_products, $order_details)
            );

            $newPrepaid = (float) ($headerPayload['prepaid_amount'] ?? $order->prepaid_amount ?? 0);
            $prepaidWillChange = abs($newPrepaid - (float) ($order->prepaid_amount ?? 0)) > 0.009;
            if ($prepaidWillChange) {
                OrderEditAccountingService::$suppressPrepaidDeltaJournal = true;
            }

            $order->update($headerPayload);

            $oldOrder_product = OrderProduct::where('order_id', $request->order_id)->get();

            $insertData = [];
            foreach ($oldOrder_product as $od) {
                $insertData[] = [
                    'order_id' => $request->order_id,
                    'category_id' => $od['category_id'],
                    'quantity' => $od['quantity'],
                    'shipped_quantity' => $od['shipped_quantity'],
                    'price' => $od['price'],
                    'total_price' => $od['total_price'],
                    'special_details' => $od['special_details'],
                    'updated_by' => auth()->user()->name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if ($this->isVariableCollectionOrderStatus($orderStatus)) {
                    Category::find($od->category_id)->increment('quantity', $od['shipped_quantity']);
                }
            }
            OrderProductArchive::insert($insertData);

            OrderDetails::where('order_id', $request->order_id)->increment('edits', 1);
            OrderProduct::where('order_id', $request->order_id)->delete();

            foreach ($order_details as $od) {
                $newOrderProduct = OrderProduct::create(
                    [
                        'order_id' => $request->order_id,
                        'category_id' => $od['category_id'],
                        'quantity' => $od['quantity'],
                        'special_details' => $od['special_details'] ?? null,
                        // 'shipped_quantity' => $od['shipped_quantity'],
                        'price' => $od['price'],
                        'total_price' => $od['total']
                    ]

                );
                if ($this->isVariableCollectionOrderStatus($orderStatus)) {
                    $newOrderProduct->shipped_quantity = $od['quantity'];
                    $newOrderProduct->save();
                    Category::find($od['category_id'])->increment('quantity', -$od['quantity']);
                }
            }

            $paymentType = $request->input('payment_type', 'bank');
            $paymentSourceId = match ($paymentType) {
                'safe' => $request->input('safe_id'),
                'service_account' => $request->input('service_account_id'),
                default => $request->input('bank') ?? $request->input('bank_id') ?? $bank_id,
            };

            if ($this->isVariableCollectionOrderStatus($orderStatus)) {
                $shippingRows = ShippingCompanyDetails::where('order_id', $orderID)
                    ->whereIn('status', self::SHIPPING_DETAIL_OPEN_COLLECTION_STATUSES)
                    ->where('is_done', 0)
                    ->get();
                if ($shippingRows->isNotEmpty()) {
                    $sumOld = (float) $shippingRows->sum('amount');
                    $newTotal = (double) $request->net_total;
                    $allocated = 0.0;
                    $i = 0;
                    $count = $shippingRows->count();
                    foreach ($shippingRows as $row) {
                        ++$i;
                        $old = (float) $row->amount;
                        $share = $count === 1
                            ? $newTotal
                            : ($sumOld > 0.0001
                                ? round($newTotal * ($old / $sumOld), 3)
                                : round($newTotal / $count, 3));
                        if ($i === $count) {
                            $share = round($newTotal - $allocated, 3);
                        }
                        $allocated = round($allocated + $share, 3);
                        $row->old_amount = $old;
                        $row->amount = $share;
                        $row->update();
                        ShippingCompany::find($row->shipping_company_id)->increment('balance', -$old + $share);
                    }
                }
                if ($request->filled('changed_collect_note')) {
                    Order::where('id', $id)->update([
                        'collect_note' => $request->input('changed_collect_note'),
                    ]);
                }
            }

            $action = $orderEditApplyService->buildTrackingAction($editorName, $auditChanges);
            $this->insertTracking($id, $action, auth()->user()->id, now());

            $autoNote = $orderEditApplyService->buildAutoChangeNote($auditChanges);
            if ($autoNote !== null) {
                $this->insertNote($orderID, auth()->user()->id, $autoNote, 'تعديل الطلب', now());
            }

            if ($request->has('order_notes') && $request->order_notes != '') {
                $note = $request->order_notes;
                $added_from = 'تعديل الطلب';
                $this->insertNote($orderID, auth()->user()->id, $note, $added_from, now());
            }

            $order_details = OrderDetails::where('order_id', $id)->first();
            $order_details->status_date = date('Y-m-d');
            $order_details->save();

            $editorUserId = (int) auth()->user()->id;
            DB::afterCommit(function () use ($orderEditAccountingService, $orderID, $accountingBefore, $editorUserId, $paymentType, $paymentSourceId) {
                try {
                    $orderEditAccountingService->reconcileAfterEdit(
                        $orderID,
                        $accountingBefore,
                        $editorUserId,
                        is_string($paymentType) ? $paymentType : null,
                        $paymentSourceId
                    );
                } finally {
                    OrderEditAccountingService::$suppressPrepaidDeltaJournal = false;
                }
            });

            DB::commit();
            return response()->json(['message' => 'success'], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function cancelOrderLines(Request $request, $id, OrderLineCancellationService $lineCancellationService)
    {
        $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.order_product_id' => 'required|integer|min:1',
            'lines.*.quantity' => 'required|numeric|min:0.001',
            'lines.*.reason' => 'required|string|min:2|max:1000',
            'note' => 'nullable|string|max:2000',
        ]);

        $order = Order::query()->find($id);
        if (! $order) {
            return response()->json(['message' => 'not found'], 404);
        }

        try {
            $result = $lineCancellationService->cancelLines(
                $order,
                $request->input('lines'),
                (int) auth()->id(),
                $request->input('note'),
            );

            return response()->json([
                'message' => $result['full_cancelled'] ? 'تم إلغاء الطلب بالكامل' : 'تم إلغاء الأصناف',
                'full_cancelled' => $result['full_cancelled'],
                'cancelled_amount' => $result['cancelled_amount'],
                'order' => $result['order'],
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function adjustPrepaid(Request $request, $id, OrderPrepaidAdjustmentService $prepaidAdjustmentService)
    {
        if (! $this->userCanManageOrderPrepaid()) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $request->validate([
            'action' => 'required|in:reverse,change_source',
            'payment_type' => 'required_if:action,change_source|in:bank,safe,service_account',
            'bank_id' => 'nullable|integer|min:1',
            'safe_id' => 'nullable|integer|min:1',
            'service_account_id' => 'nullable|integer|min:1',
            'note' => 'nullable|string|max:2000',
        ]);

        $order = Order::query()->find($id);
        if (! $order) {
            return response()->json(['message' => 'not found'], 404);
        }

        DB::beginTransaction();
        try {
            if ($request->input('action') === 'reverse') {
                $order = $prepaidAdjustmentService->reversePrepaid(
                    $order,
                    (int) auth()->id(),
                    $request->input('note'),
                );
                $message = 'تم عكس مبلغ تحت الحساب';
            } else {
                $paymentType = (string) $request->input('payment_type');
                $sourceId = match ($paymentType) {
                    'safe' => (int) $request->input('safe_id'),
                    'service_account' => (int) $request->input('service_account_id'),
                    default => (int) $request->input('bank_id'),
                };

                if ($sourceId <= 0) {
                    throw new \InvalidArgumentException('يجب اختيار مصدر الدفع');
                }

                $order = $prepaidAdjustmentService->changePrepaidSource(
                    $order,
                    (int) auth()->id(),
                    $paymentType,
                    $sourceId,
                    $request->input('note'),
                );
                $message = 'تم تغيير مصدر الدفع';
            }

            DB::commit();

            return response()->json([
                'message' => $message,
                'order' => $order->load('bank'),
            ], 200);
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function refuseOrder(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $user_id = auth()->user()->id;
            $order = Order::with('order_details')->find($id);
            if (!$order) {
                return response()->json(['message' => 'not found'], 404);
            }

            if (! $this->orderAllowsRefuseReceipt($order)) {
                return response()->json(['message' => ' حالة الطلب الحاليه ' . $order->order_status], 422);
            }

            if (! $this->orderProfileAllows('refuse_maintain')) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            $order_details = OrderDetails::where('order_id', $id)->first();
            if (! $this->orderAllowsRefuseReceipt($order)) {
                return response()->json(['message' => 'you can\'t do that'], 403);
            }

            $this->reverseDeliveryAccountingIfNeeded($order, $order_details);

            $shippingRows = ShippingCompanyDetails::where('order_id', $id)
                ->whereIn('status', self::SHIPPING_DETAIL_OPEN_COLLECTION_STATUSES)
                ->where('is_done', 0)
                ->orderBy('id')
                ->get();
            if ($shippingRows->isNotEmpty()) {
                foreach ($shippingRows as $orderdata) {
                    $orderdata->is_done = 1;
                    $orderdata->save();

                    $shipping_company_id = (int) $orderdata->shipping_company_id;
                    $order_id = $id;
                    $shipping_date = $order_details->shipping_date;
                    $status = 'رفض استلام';
                    $amount = (float) -$orderdata->amount;
                    DB::statement('CALL shipping_company_procedure(?, ?, ?, ?, ?, ?, ?)', [
                        $shipping_company_id,
                        $order_id,
                        $shipping_date,
                        $status,
                        $amount,
                        auth()->user()->name,
                        now()
                    ]);
                }

                $shipping_company = ShippingCompany::find($order_details?->shipping_company_id);
                if ($shipping_company && $request->bank != 'الخزينة') {
                    $amount = (float)$request->amount;
                    $details = ' تحصيل من شركة شحن ' . $shipping_company->name . ' لرفض استلام طلب ';
                    $ref = $order->id;
                    $type = 'الطلبات';
                    $this->updateBankBalance(
                        $request->bank,
                        $amount,
                        $order->id,
                        $user_id,
                        $details,
                        $ref,
                        $type,
                        now(),
                        $this->resolveShippingReceivableAccountId($shipping_company),
                    );
                }
            }

            if ($request->getorder == 'true') {
                $products = OrderProduct::where('order_id', $id)->get();
                $totalCogsReturn = 0;
                $restoreByInv = [];
                foreach ($products as $op) {
                    if ($op->quantity > 0 && (float) $op->shipped_quantity > 0) {
                        $avgCost = CategoryInventoryCostService::resolveReferenceUnitCost((int) $op->category_id);
                        $lineRet = $avgCost * (float) $op->shipped_quantity;
                        $totalCogsReturn += $lineRet;
                        $invAcc = \App\Models\TreeAccount::resolveInventoryAccountForCategoryId((int) $op->category_id);
                        if ($invAcc && $lineRet > 0.000001) {
                            $restoreByInv[$invAcc->id] = ($restoreByInv[$invAcc->id] ?? 0) + $lineRet;
                        }
                    }
                }
                if ($totalCogsReturn > 0.00001) {
                    if (count($restoreByInv) === 0) {
                        $fb = \App\Models\TreeAccount::resolveInventoryAccount();
                        if ($fb) {
                            $restoreByInv[$fb->id] = $totalCogsReturn;
                        }
                    }
                    app(InventoryGlPostingService::class)->postSalesReturnInventoryRestoreByWarehouse(
                        $restoreByInv,
                        'رفض استلام — إرجاع تكلفة للمخزون — طلب ' . $id,
                        auth()->id()
                    );
                }
                foreach ($products as $product) {
                    if ($product->quantity > 0 && (float) $product->shipped_quantity > 0) {
                        $category_id = (int)$product->category_id;
                        $invoice_number = $id;
                        $type = 'رفض استلام طلب';
                        $quantity = (float)$product->shipped_quantity;
                        $price = (float)$product->price;
                        DB::statement('CALL category_procedure(?, ?, ?, ?, ?, ?, ?)', [
                            $category_id,
                            $invoice_number,
                            $type,
                            $quantity,
                            $price,
                            auth()->user()->name,
                            now()
                        ]);

                        Category::find($category_id)->increment('sell_total_price', - ($product->price * $product->shipped_quantity));

                        $product->shipped_quantity = 0;
                        $product->save();
                    }
                }
            } else {
                $admin  = User::whereIn('department', ['Admin', 'admin'])->first();

                if ($admin && auth()->id() !== $admin->id) {
                    Notification::create([
                        'send_from' =>  auth()->id(),
                        'send_to' => $admin->id,
                        'type' => 'مرتجع',
                        'ref' => $order->id,
                        'order_id' => $order->id,
                        'note' => ' لم يتم استلام المرتجع ' . $request->reasoncat,
                    ]);
                }

                Note::create([
                    'order_id' => $id,
                    'user_id' => auth()->user()->id,
                    'note' => $request->reasoncat,
                    'added_from' => 'رفض استلام',
                    'is_problem' => true
                ]);
            }

            $order->order_status = 'رفض استلام';
            $order_details->canceled_date = date('Y-m-d');
            $order_details->status_date = date('Y-m-d');

            $action = 'رفض استلام';
            $this->insertTracking($order->id, $action, $user_id, now());

            if ($request->has('note') && $request->note != '') {
                $note = $request->note;
                $added_from = 'رفض استلام';
                $this->insertNote($order->id, $user_id, $note, $added_from, now());
            }

            $admin  = User::whereIn('department', ['Admin', 'admin'])->first();

            if ($admin && auth()->id() !== $admin->id) {
                Notification::create([
                    'send_from' =>  auth()->id(),
                    'send_to' => $admin->id,
                    'type' => 'رفض استلام طلب',
                    'ref' => $order->id,
                    'order_id' => $order->id,
                    'note' => $request->note,
                ]);
            }

            $order->save();
            $order_details->reviewed = 0;
            $order_details->save();
            if ($order->order_status === 'رفض استلام') {
                app(OrderFinancialStateService::class)->syncFromOrder($order->fresh(['order_details']), $order_details);
            }
            DB::commit();
            return response()->json(['message' => 'success'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function change_status(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $user_id = auth()->user()->id;
            $order = Order::find($id);
            if (!$order) {
                return response()->json(['message' => 'not found'], 404);
            }
            $order_details = OrderDetails::where('order_id', $id)->first();
            if (! $order_details) {
                return response()->json(['message' => 'تفاصيل الطلب غير موجودة'], 422);
            }
            if ($request->query('status') == 'cancel') {

                $validStatuses = ['طلب مؤكد', 'طلب جديد', 'مؤجل'];

                if (!in_array($order->order_status, $validStatuses)) {
                    return response()->json(['message' => ' حالة الطلب الحاليه ' . $order->order_status], 422);
                }

                if ($order->customer_type == 'شركة') {
                    if ($request->has('amount')) {
                        $customer = customerCompany::find($order->company_id);

                        $action = ' خصم مبلغ ' . $request->amount . ' لالغاء الطلب ';
                        $this->insertTracking($order->id, $action, $user_id, now());

                        $company_id = $order->company_id;
                        $amount = (float)$request->amount;
                        $ref = $id;
                        $details = ' خصم مبلغ الغاء طلب ' . $id;
                        $type = 'الطلبات';
                        DB::statement('CALL update_customer_company_balance(?, ?, ?, ?, ?, ?, ?, ?)', [
                            $company_id,
                            $amount,
                            $bank_id = null,
                            $ref,
                            $details,
                            $type,
                            $user_id,
                            now()
                        ]);

                        if ($request->bank > 0) {
                            $bank = Bank::find($request->bank);
                            if ($bank && $request->amount > $order->prepaid_amount) {
                                $customer = customerCompany::find($order->company_id);
                                $amount = (float)$request->amount - $order->prepaid_amount;
                                $details = ' تحصيل مبلغ الغاء طلب رقم ' . $id . ' من عميل شركة ' . $customer->name;
                                $ref = $order->id;
                                $type = 'الطلبات';
                                $this->updateBankBalance($bank->id, $amount, $order->id, $user_id, $details, $ref, $type, now());

                                $company_id = $order->company_id;
                                $amount = (float)- ($request->amount - $order->prepaid_amount);
                                $ref = $id;
                                $details = ' تحصيل مبلغ الغاء طلب رقم ' . $id . ' في خزينة ' . $bank->name;
                                $type = 'الطلبات';
                                DB::statement('CALL update_customer_company_balance(?, ?, ?, ?, ?, ?, ?, ?)', [
                                    $company_id,
                                    $amount,
                                    $bank->id,
                                    $ref,
                                    $details,
                                    $type,
                                    $user_id,
                                    now()
                                ]);
                            }
                        }
                    }
                }
                $cancelAccounting = app(OrderCancellationAccountingService::class);
                $cancelAccounting->handleCancellation(
                    $order,
                    $user_id,
                    $cancelAccounting->refundOptionsFromRequest($request),
                );

                $order->order_status = 'ملغي';
                $order_details->canceled_date = date('Y-m-d');
                $order_details->status_date = date('Y-m-d');

                $action = 'طلب ملغي';
                $this->insertTracking($order->id, $action, $user_id, now());
                $admin  = User::whereIn('department', ['Admin', 'admin'])->first();

                if ($admin && auth()->id() !== $admin->id) {
                    Notification::create([
                        'send_from' =>  auth()->id(),
                        'send_to' => $admin->id,
                        'type' => 'الغاء طلب',
                        'ref' => $order->id,
                        'order_id' => $order->id,
                        'note' => $request->note,
                    ]);
                }

                if ($request->has('note') && $request->note != '') {
                    $note = $request->note;
                    $added_from = 'الغاء الطلب';
                    $this->insertNote($order->id, $user_id, $note, $added_from, now());
                }
            }

            if ($request->query('status') == 'refused') {

                if (! $this->orderAllowsRefuseReceipt($order)) {
                    return response()->json(['message' => ' حالة الطلب الحاليه ' . $order->order_status], 422);
                }

                if (! $this->orderProfileAllows('refuse_maintain')) {
                    return response()->json(['message' => 'Forbidden'], 403);
                }

                if ($order->customer_type !== 'شركة') {
                    return response()->json(['message' => 'رفض استلام للأفراد يتم عبر مسار رفض الاستلام المخصص'], 422);
                }

                $amountFromShipping = ShippingCompanyDetails::where('order_id', $id)
                    ->where('is_done', 0)
                    ->whereIn('status', self::SHIPPING_DETAIL_OPEN_COLLECTION_STATUSES)
                    ->sum('amount');
                $this->reverseDeliveryAccountingIfNeeded($order, $order_details);
                $order_products = OrderProduct::where('order_id', $id)->get();
                foreach ($order_products as $product) {
                    $shippedQty = (float) $product->shipped_quantity;
                    if ($shippedQty > 0.0001) {
                        $category_id = (int) $product->category_id;
                        $invoice_number = $id;
                        $type = 'رفض استلام طلب';
                        $quantity = $shippedQty;
                        $price = (float) $product->price;
                        DB::statement('CALL category_procedure(?, ?, ?, ?, ?, ?, ?)', [
                            $category_id,
                            $invoice_number,
                            $type,
                            $quantity,
                            $price,
                            auth()->user()->name,
                            now()
                        ]);

                        Category::find($category_id)->increment('sell_total_price', (float) - ($product->price * $shippedQty));
                    }
                    $product->shipped_quantity = 0;
                    $product->save();
                }
                    $shippingDetails = ShippingCompanyDetails::where('order_id', $id)
                        ->whereIn('status', self::SHIPPING_DETAIL_OPEN_COLLECTION_STATUSES)
                        ->where('is_done', 0)
                        ->get();
                    if ($order->customer_type == 'شركة') {
                        foreach ($shippingDetails as $elm) {
                            // $orderdata = ShippingCompanyDetails::where('id',$elm->id)->first();
                            $elm->is_done = 1;
                            $elm->save();

                            $shipping_company_id = (int)$elm->shipping_company_id;
                            $order_id = $id;
                            $shipping_date = $order_details->shipping_date;
                            $status = 'رفض استلام';
                            $amount = (float)-$elm->amount;
                            DB::statement('CALL shipping_company_procedure(?, ?, ?, ?, ?, ?, ?)', [
                                $shipping_company_id,
                                $order_id,
                                $shipping_date,
                                $status,
                                $amount,
                                auth()->user()->name,
                                now()
                            ]);
                        }

                        $company_id = $order->company_id;
                        $amount = (float)- ($order->net_total + $order->prepaid_amount - $amountFromShipping);
                        $ref = $id;
                        $details = ' ارجاع ثمن طلب رقم ' . $id;
                        $type = 'الطلبات';
                        DB::statement('CALL update_customer_company_balance(?, ?, ?, ?, ?, ?, ?, ?)', [
                            $company_id,
                            $amount,
                            $bank_id = null,
                            $ref,
                            $details,
                            $type,
                            $user_id,
                            now()
                        ]);


                        if ($request->has('amount')) {
                            $amount = (float)$request->amount;
                            $details = ' خصم مبلغ لرفض استلام ' . $id;
                            DB::statement('CALL update_customer_company_balance(?, ?, ?, ?, ?, ?, ?, ?)', [
                                $company_id,
                                $amount,
                                $bank_id = null,
                                $ref,
                                $details,
                                $type,
                                $user_id,
                                now()
                            ]);

                            $action = ' خصم مبلغ ' . $request->amount . ' لرفض الاستلام ';
                            $this->insertTracking($order->id, $action, $user_id, now());

                            if ($request->bank > 0) {
                                $bank = Bank::find($request->bank);

                                if ($bank) {
                                    $customer = customerCompany::find($order->company_id);

                                    $details = ' تحصيل مبلغ رفض استلام طلب رقم ' . $id . ' من عميل شركة ' . $customer->name;
                                    $type = 'الطلبات';
                                    $this->updateBankBalance($request->bank, $amount, $order->id, $user_id, $details, $ref, $type, now());

                                    $amount = (float)- ($request->amount);
                                    $details = ' تحصيل مبلغ رفض استلام طلب رقم ' . $id . ' في خزينة ' . $bank->name;
                                    DB::statement('CALL update_customer_company_balance(?, ?, ?, ?, ?, ?, ?, ?)', [
                                        $company_id,
                                        $amount,
                                        $bank->id,
                                        $ref,
                                        $details,
                                        $type,
                                        $user_id,
                                        now()
                                    ]);
                                }
                            }
                        }
                    }

                    $order->order_status = 'رفض استلام';
                    $order_details->canceled_date = date('Y-m-d');
                    $order_details->status_date = date('Y-m-d');

                    $action = 'رفض استلام';
                    $this->insertTracking($order->id, $action, $user_id, now());

                    if ($request->has('note') && $request->note != '') {
                        $note = $request->note;
                        $added_from = 'رفض استلام';
                        $this->insertNote($order->id, $user_id, $note, $added_from, now());
                    }

                    $admin  = User::where('department', 'admin')->first();

                    if (auth()->id() !== $admin->id) {
                        Notification::create([
                            'send_from' =>  auth()->id(),
                            'send_to' => $admin->id,
                            'type' => 'رفض استلام طلب',
                            'ref' => $order->id,
                            'order_id' => $order->id,
                            'note' => $request->note,
                        ]);
                    }
            } else if ($request->query('status') == 'postponed') {

                if (!(in_array($order->order_status, ['طلب جديد', 'طلب مؤكد', 'تم شحن']))) {
                    return response()->json(['message' => ' حالة الطلب الحاليه ' . $order->order_status], 422);
                }

                if (! $this->orderProfileAllows('postpone_order')) {
                    return response()->json(['message' => 'Forbidden'], 403);
                }

                if ($order->order_status == "تم شحن") {

                    $order_products = OrderProduct::where('order_id', $id)->get();
                    foreach ($order_products as $product) {
                        // $order_product = OrderProduct::find($product['id']);
                        $product->shipped_quantity = 0;
                        $product->save();

                        $category_id = $product->category_id;
                        $invoice_number = $id;
                        $type = 'تاجيل طلب';
                        $quantity = (float)$product['quantity'];
                        $price = (float)$product['price'];
                        DB::statement('CALL category_procedure(?, ?, ?, ?, ?, ?, ?)', [
                            $category_id,
                            $invoice_number,
                            $type,
                            $quantity,
                            $price,
                            auth()->user()->name,
                            now()
                        ]);

                        Category::find($category_id)->increment('sell_total_price', - ((float)$product['quantity'] * (float)$product['price']));
                    }

                    $shippingDetails = ShippingCompanyDetails::where('order_id', $id)->where('status', 'تم شحن')->where('is_done', 0)->get();

                    foreach ($shippingDetails as $elm) {
                        $shipping_company = ShippingCompany::find($elm->shipping_company_id);
                        // $orderdata = ShippingCompanyDetails::where('id',$elm->id)->first();
                        $elm->is_done = 1;
                        $elm->save();

                        $shipping_company->balance = $shipping_company->balance - $elm->amount;
                        $shipping_company->save();
                    }

                    if ($order->customer_type === 'شركة') {
                        $paymentAmount = DB::table('customer_company_details')->where('customer_company_id', $order->company_id)
                            ->where('ref', $order->id)->latest('created_at')->first();
                        $company_id = $order->company_id;
                        $amount = -(float)$paymentAmount->amount;
                        $ref = $id;
                        $details = ' تعدبل رصيد العميل بعد تاجيل الطلب رقم ' . $id;
                        $type = 'الطلبات';
                        DB::statement('CALL update_customer_company_balance(?, ?, ?, ?, ?, ?, ?, ?)', [
                            $company_id,
                            $amount,
                            $bank_id = null,
                            $ref,
                            $details,
                            $type,
                            $user_id,
                            now()
                        ]);
                    }

                    $admin  = User::where('department', 'admin')->first();

                    if (auth()->id() !== $admin->id) {
                        Notification::create([
                            'send_from' =>  auth()->id(),
                            'send_to' => $admin->id,
                            'type' => 'تأجيل طلب',
                            'ref' => $order->id,
                            'order_id' => $order->id,
                            'note' => $request->note,
                        ]);
                    }
                }
                $order->order_status = 'مؤجل';
                $order_details->postponed_date = date('Y-m-d');
                $order_details->status_date = date('Y-m-d');
                $order_details->postponed = $order_details->postponed + 1;

                $action = 'طلب مؤجل';
                $this->insertTracking($order->id, $action, $user_id, now());

                if ($request->has('note') && $request->note != '') {
                    $note = $request->note;
                    $added_from = 'تاجيل الطلب';
                    $this->insertNote($order->id, $user_id, $note, $added_from, now());
                }
            } else if ($request->query('status') == 'archived') {

                if (!(in_array($order->order_status, ['طلب جديد', 'طلب مؤكد', 'مؤجل', 'ملغي']))) {
                    return response()->json(['message' => ' حالة الطلب الحاليه ' . $order->order_status], 422);
                }

                $department = auth()->user()->department;
                if (!($department == 'Admin')) {
                    return response()->json(['message' => 'Forbidden'], 403);
                }

                $order->order_status = 'أرشيف';
                $order_details->archived_date = date('Y-m-d');
                $order_details->status_date = date('Y-m-d');

                $action = 'طلب مؤرشف';
                $this->insertTracking($order->id, $action, $user_id, now());

                if ($request->has('note') && $request->note != '') {
                    $note = $request->note;
                    $added_from = 'ارشفة الطلب';
                    $this->insertNote($order->id, $user_id, $note, $added_from, now());
                }
            } else if ($request->query('status') == 'renew') {
                if (!(in_array($order->order_status, ['رفض استلام', 'أرشيف', 'مؤجل', 'ملغي']))) {
                    return response()->json(['message' => ' حالة الطلب الحاليه ' . $order->order_status], 422);
                }

                $department = auth()->user()->department;
                if (! $this->orderProfileAllows('change_status')) {
                    return response()->json(['message' => 'Forbidden'], 403);
                }

                $oldPrepaid = round((float) ($order->prepaid_amount ?? 0), 3);
                $policy = (string) $request->input('prepaidPolicy', $oldPrepaid > 0.0001 ? '' : 'none');

                if ($oldPrepaid > 0.0001 && ! in_array($policy, ['keep', 'return'], true)) {
                    return response()->json([
                        'message' => 'يجب تحديد التعامل مع مبلغ تحت الحساب السابق (إبقاء أو إرجاع للعميل)',
                    ], 422);
                }

                if ($policy === 'return' && $oldPrepaid > 0.0001) {
                    if (! $request->has('moneyReturnedStatus')) {
                        return response()->json(['message' => 'يجب تحديد حالة إرجاع مبلغ تحت الحساب'], 422);
                    }
                    $this->refundPrepaidUnderAccount($order, $request, (int) $id, $user_id, 'إرجاع مبلغ تحت الحساب عند تجديد الطلب');
                    $order->net_total = round((float) $order->net_total + $oldPrepaid, 3);
                    $order->prepaid_amount = 0;
                    $order->bank_id = null;
                } elseif ($policy === 'keep' && $oldPrepaid > 0.0001) {
                    $action = 'تجديد الطلب مع الإبقاء على مبلغ تحت الحساب ('.$oldPrepaid.')';
                    $this->insertTracking($order->id, $action, $user_id, now());
                } elseif ($oldPrepaid > 0.0001) {
                    $order->net_total = round((float) $order->net_total + $oldPrepaid, 3);
                    $order->prepaid_amount = 0;
                    $order->bank_id = null;
                }

                $renewAmount = round((float) $request->input('renewAmount', 0), 3);
                $preservePrepaidCollection = $policy === 'keep' && $oldPrepaid > 0.0001;
                if ($renewAmount > 0.0001) {
                    if ($policy === 'keep') {
                        return response()->json([
                            'message' => 'لا يمكن إدخال دفعة جديدة مع خيار الإبقاء على الدفعة السابقة',
                        ], 422);
                    }

                    $order->order_status = 'طلب جديد';
                    $order_details->renew_date = date('Y-m-d');
                    $order_details->status_date = date('Y-m-d');
                    $this->resetOrderDetailsForRenew($order_details);

                    try {
                        $this->applyRenewPrepaid($order, $order_details, $request, $renewAmount, $user_id);
                    } catch (\InvalidArgumentException $e) {
                        return response()->json(['message' => $e->getMessage()], 422);
                    }
                } else {
                    $order->order_status = 'طلب جديد';
                    $order_details->renew_date = date('Y-m-d');
                    $order_details->status_date = date('Y-m-d');
                    $this->resetOrderDetailsForRenew($order_details, $preservePrepaidCollection);
                }

                $action = 'تم تجديد الطلب';
                $this->insertTracking($order->id, $action, $user_id, now());

                if ($request->has('note') && $request->note != '') {
                    $note = $request->note;
                    $added_from = 'تجديد الطلب';
                    $this->insertNote($order->id, $user_id, $note, $added_from, now());
                }

                $order->save();
                app(OrderFinancialStateService::class)->syncFromOrder($order->fresh(['order_details']), $order_details);

                try {
                    $fresh = Order::with(['order_products', 'order_details'])->find($order->id);
                    if ($fresh) {
                        app(SalesOrderAccountingService::class)->refreshOrderRecognition($fresh, rebuildPrepaid: true);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Renew order: recognition refresh failed', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $order->save();
            $order_details->reviewed = 0;
            $order_details->save();
            if (in_array($order->order_status, ['رفض استلام', 'ملغي'], true)) {
                app(OrderFinancialStateService::class)->syncFromOrder($order->fresh(['order_details']), $order_details);
            }
            DB::commit();
            return response()->json(['message' => 'success'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function confirm(Request $request, $id)
    {

        $request->validate([
            'date' => 'required',
            'line_id' => 'required|numeric|exists:shippinglines,id',
        ]);
        DB::beginTransaction();
        try {
            $order = Order::find($id);
            if (!$order) {
                return response()->json(['message' => 'not found'], 404);
            }

            if (!in_array($order->order_status, ['جديد', 'طلب جديد', 'تم الصيانة'])) {
                return response()->json(['message' => ' حالة الطلب الحاليه ' . $order->order_status], 422);
            }

            if ($order->shopify_needs_product_review) {
                DB::rollback();

                return response()->json([
                    'message' => 'هذا الطلب يحتوي منتجات Shopify غير مربوطة بأصناف ERP. راجع الطلب واربط الصنف الصحيح قبل التأكيد.',
                ], 422);
            }

            $user_id = auth()->user()->id;
            $order->order_status = 'طلب مؤكد';
            OrderDetails::updateOrCreate(
                ['order_id' => $id],
                [
                    'need_by_date' => $request->date,
                    'shipping_line_id' => $request->line_id,
                    'confirm_date' => date('Y-m-d'),
                    'status_date' => date('Y-m-d'),
                    'reviewed' => 0,
                ]
            );
            $order->save();

            $action = 'تم تاكيد الطلب';
            $this->insertTracking($order->id, $action, $user_id, now());

            if ($order->order_type == 'طلب صيانة') {
                OrderMaintenReason::create([
                    'order_id' => $id,
                    'order_status' => $order->order_status,
                    'mainten_reason' => $request->maintenReason,
                ]);
            }

            if ($request->has('note') && $request->note != '') {
                $note = $request->note;
                $added_from = 'تأكيد الطلب';
            $this->insertNote($order->id, $user_id, $note, $added_from, now());
            }


            // $whatsapp = new \App\Services\WhatsAppService();
            // $recipient = '+201550191001';

            // $orderId = $order->id;
            // $netTotal = $order->net_total;

            // $whatsapp->sendOrderConfirmationMessage($recipient, $orderId, $netTotal);

            // return;


            DB::commit();
            return response()->json(['message' => 'success'], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }


    public function whatsapp(Request $request)
    {
        $from = $request->input('From');
        $body = trim($request->input('Body'));

        $order = Order::where('phone', $from)->latest()->first();

        if ($order->is_confirmed || $order->is_cancelled) {
            return response('', 200);
        }

        $twilio = new MessagingResponse();

        if ($body === '1') {
            $twilio->message("✅ تم تأكيد الطلب");
        } elseif ($body === '2') {
            $twilio->message("❌ تم إلغاء الطلب");
        } else {
            $twilio->message("الرجاء الرد بـ:\n1️⃣ لتأكيد الطلب\n2️⃣ لإلغاء الطلب");
        }

        return response($twilio, 200)->header('Content-Type', 'text/xml');
    }

    public function ship_order(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order) {
            return response()->json(['message' => 'not found'], 404);
        }

        if (
            ($order->customer_type == 'شركة' && !in_array($order->order_status, ['طلب جديد', 'طلب مؤكد', 'شحن جزئي', 'مؤجل'])) ||
            ($order->customer_type == 'افراد' && !in_array($order->order_status, ['طلب مؤكد', 'مؤجل']))
        ) {
            return response()->json(['message' => ' حالة الطلب الحاليه ' . $order->order_status], 422);
        }

        if ($order->shopify_needs_product_review) {
            return response()->json([
                'message' => 'هذا الطلب يحتوي منتجات Shopify غير مربوطة بأصناف ERP. راجع الطلب واربط الصنف الصحيح قبل الشحن.',
            ], 422);
        }

        $request->validate([
            'date' => 'required',
            'company_id' => 'required|numeric|exists:shipping_companies,id',
            'collection_company_id' => 'nullable|numeric|exists:shipping_companies,id',
            'collection_provider_type' => 'nullable|in:none,shipping_company,courier,collection_company,employee',
            'collection_provider_id' => 'nullable|integer|min:1',
            'productsToShip' => 'required',
            'shipping_receivable_amount' => 'nullable|numeric|min:0',
            'collection_receivable_amount' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $user_id = auth()->user()->id;
            $receivableSnapshot = null;
            if (in_array($order->order_type, self::ORDER_TYPES_INVENTORY_SHIP, true)) {
                $total = 0;
                $finshied = true;
                $productsToShip = json_decode($request->productsToShip, true);
                if (! is_array($productsToShip)) {
                    DB::rollBack();

                    return response()->json([
                        'message' => 'تنسيق بيانات الأصناف المراد شحنها غير صالح.',
                    ], 422);
                }
                $totalCogs = 0;
                $cogsByInvAcc = [];
                foreach ($productsToShip as $product) {
                    $order_product = OrderProduct::find($product['id'] ?? null);
                    if (! $order_product) {
                        DB::rollBack();

                        return response()->json([
                            'message' => 'أحد أسطر الطلب غير موجود (معرّف السطر: '
                                . (string) ($product['id'] ?? '')
                                . ').',
                        ], 422);
                    }

                    $category_id = (int) $order_product->category_id;
                    if ($category_id <= 0 || ! Category::query()->whereKey($category_id)->exists()) {
                        DB::rollBack();

                        return response()->json([
                            'message' => 'بند الطلب يشير لتصنيف مخزني غير موجود (رقم التصنيف: '
                                . $category_id
                                . '، الطلب '
                                . (string) $id
                                . '، سطر الطلب '
                                . (string) $order_product->id
                                . '). أصلح الصنف في الطلب أو استعد التصنيف المحذوف من قاعدة البيانات.',
                        ], 422);
                    }

                    $remainingBeforeShip = (float) $order_product->quantity
                        - (float) ($order_product->shipped_quantity ?? 0)
                        - (float) ($order_product->cancelled_quantity ?? 0);
                    $shipQty = (float) ($product['quantity'] ?? 0);
                    if ($shipQty > $remainingBeforeShip + 0.0001) {
                        DB::rollBack();

                        return response()->json([
                            'message' => 'كمية الشحن ('.$shipQty.') أكبر من المتبقي ('.$remainingBeforeShip.') لسطر الطلب #'.$order_product->id,
                        ], 422);
                    }

                    $avgCost = CategoryInventoryCostService::averageCostForCategoryIssue($category_id);
                    $lineCogs = $avgCost * (float) $product['quantity'];
                    $totalCogs += $lineCogs;
                    $invAcc = \App\Models\TreeAccount::resolveInventoryAccountForCategoryId($category_id);
                    if ($invAcc && $lineCogs > 0.000001) {
                        $cogsByInvAcc[$invAcc->id] = ($cogsByInvAcc[$invAcc->id] ?? 0) + $lineCogs;
                    }
                    $order_product->shipped_quantity += (float)$product['quantity'];
                    $order_product->save();


                    $invoice_number = $id;
                    $type = 'شحن طلب';
                    $quantity = -(float)$product['quantity'];
                    $price = $order_product->price;
                    DB::statement('CALL category_procedure(?, ?, ?, ?, ?, ?, ?)', [
                        $category_id,
                        $invoice_number,
                        $type,
                        $quantity,
                        $price,
                        auth()->user()->name,
                        now()
                    ]);

                    Category::find($category_id)->increment('sell_total_price', ($order_product->price * (float)$product['quantity']));

                    $total += (int)$product['quantity'] * $order_product->price;
                }

                $allOrderProducts = OrderProduct::query()->where('order_id', $id)->get();
                foreach ($allOrderProducts as $lineProduct) {
                    $remaining = (float) $lineProduct->quantity
                        - (float) ($lineProduct->shipped_quantity ?? 0)
                        - (float) ($lineProduct->cancelled_quantity ?? 0);
                    if ($remaining > 0.009) {
                        $finshied = false;
                        break;
                    }
                }


                $shipping_company = ShippingCompany::find($request->company_id);

                if ($finshied) {
                    if ($order->customer_type == 'افراد') {
                        $this->assertReceivableAccountsLinked($order, $request, null);
                        $receivableSnapshot = $this->postShippedReceivableSegments(
                            $order,
                            (int) $request->company_id,
                            $this->resolveCollectionCompanyIdForShip($request),
                            $request->filled('shipping_receivable_amount') ? (float) $request->shipping_receivable_amount : null,
                            $request->filled('collection_receivable_amount') ? (float) $request->collection_receivable_amount : null,
                            (string) $request->date,
                            (int) $id,
                            null
                        );
                    }

                    if ($order->customer_type == 'شركة') {
                        if ($request->payment_way == 'أجل') { ////
                            $company_id = $order->company_id;
                            $amount = (float)($total + $order->vat - $order->discount);
                            $ref = $id;
                            $details = '  شحن اجل طلب رقم ' . $id;
                            $type = 'الطلبات';
                            DB::statement('CALL update_customer_company_balance(?, ?, ?, ?, ?, ?, ?, ?)', [
                                $company_id,
                                $amount,
                                $bank_id = null,
                                $ref,
                                $details,
                                $type,
                                $user_id,
                                now()
                            ]);
                        }

                        if ($request->payment_way == 'نقدي') {
                            $shipping_company_id = (float)$request->company_id;
                            $order_id = $id;
                            $shipping_date = $request->date;
                            $status = 'تم شحن';
                            $amount = (float)$request->cash;
                            DB::statement('CALL shipping_company_procedure(?, ?, ?, ?, ?, ?, ?)', [
                                $shipping_company_id,
                                $order_id,
                                $shipping_date,
                                $status,
                                $amount,
                                auth()->user()->name,
                                now()
                            ]);

                            $company_id = $order->company_id;
                            $amount = (float)(($total - $request->cash) + $order->vat - $order->discount);
                            $ref = $id;
                            $details = ' متبقي من شحن نقدي طلب رقم ' . $id . ' مع شركة شحن ' . $shipping_company->name;
                            $type = 'الطلبات';
                            DB::statement('CALL update_customer_company_balance(?, ?, ?, ?, ?, ?, ?, ?)', [
                                $company_id,
                                $amount,
                                $bank_id = null,
                                $ref,
                                $details,
                                $type,
                                $user_id,
                                now()
                            ]);
                        }
                    }

                    $action = 'تم شحن';
                    $this->insertTracking($order->id, $action, $user_id, now());

                    $order->order_status = 'تم شحن';
                    $order->save();
                } else {

                    if ($order->customer_type == 'شركة') {
                        if ($request->payment_way == 'أجل') {
                            $company_id = $order->company_id;
                            $amount = (float)$total;
                            $ref = $id;
                            $details = ' شحن جزئي اجل طلب رقم ' . $id;
                            $type = 'الطلبات';
                            DB::statement('CALL update_customer_company_balance(?, ?, ?, ?, ?, ?, ?, ?)', [
                                $company_id,
                                $amount,
                                $bank_id = null,
                                $ref,
                                $details,
                                $type,
                                $user_id,
                                now()
                            ]);
                        }

                        if ($request->payment_way == 'نقدي') {
                            $shipping_company_id = (float)$request->company_id;
                            $order_id = $id;
                            $shipping_date = $request->date;
                            $status = 'تم شحن';
                            $amount = (float)$request->cash;
                            DB::statement('CALL shipping_company_procedure(?, ?, ?, ?, ?, ?, ?)', [
                                $shipping_company_id,
                                $order_id,
                                $shipping_date,
                                $status,
                                $amount,
                                auth()->user()->name,
                                now()
                            ]);

                            $company_id = $order->company_id;
                            $amount = (float)($total - $request->cash);
                            $ref = $id;
                            $details = ' متبقي من شحن جزئي نقدي طلب رقم ' . $id . ' مع شركة شحن ' . $shipping_company->name;
                            $type = 'الطلبات';
                            DB::statement('CALL update_customer_company_balance(?, ?, ?, ?, ?, ?, ?, ?)', [
                                $company_id,
                                $amount,
                                $bank_id = null,
                                $ref,
                                $details,
                                $type,
                                $user_id,
                                now()
                            ]);
                        }
                    }

                    $action = 'تم شحن جزء من الطلب';
                    $this->insertTracking($order->id, $action, $user_id, now());

                    $order->order_status = 'شحن جزئي';
                    $order->save();
                }
                
                if ($totalCogs > 0.000001) {
                    try {
                        app(InventoryGlPostingService::class)->postCogsShipment(
                            $totalCogs,
                            $cogsByInvAcc,
                            'تكلفة البضاعة المباعة للطلب رقم ' . $order->id
                        );
                    } catch (\Throwable $e) {
                        Log::warning('COGS ship_order: postCogsShipment failed', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                // Sales revenue recognition is handled at order creation by SalesOrderAccountingService.
                // No duplicate Dr Customer / Cr Sales entry needed at shipping.
                // Only COGS (above) is posted at ship time.
            } else {
                $order->order_status = 'تم شحن';
                $order->save();
            }
            $img_name = '';
            if ($request->hasFile('shipping_image')) {
                $img = $request->file('shipping_image');
                $img_name = time() . '.' . $img->extension();
                $img->move(public_path('images'), $img_name);
            }

            if ($order->customer_type != 'شركة' && ! in_array($order->order_type, self::ORDER_TYPES_INVENTORY_SHIP, true)) {

                $action = 'تم شحن الطلب';
                $this->insertTracking($order->id, $action, $user_id, now());

                $basisAmount = (float) $order->net_total;

                $existFirstPay = ShippingCompanyDetails::where('order_id', $id)->get();

                $basisOverride = null;
                if ($order->order_type == 'طلب صيانة' && $existFirstPay->count() > 0) {
                    $collect = ShippingCompanyDetails::where('order_id', $id)->where('status', 'تم التحصيل')->get();

                    if ($collect->count() > 0) {
                        $basisAmount = (float) $order->net_total;
                    } else {
                        $basisAmount = (float) ($order->net_total - $existFirstPay[0]->amount);
                    }
                    $basisOverride = $basisAmount;
                }

                $this->assertReceivableAccountsLinked($order, $request, $basisOverride);
                $receivableSnapshot = $this->postShippedReceivableSegments(
                    $order,
                    (int) $request->company_id,
                    $this->resolveCollectionCompanyIdForShip($request),
                    $request->filled('shipping_receivable_amount') ? (float) $request->shipping_receivable_amount : null,
                    $request->filled('collection_receivable_amount') ? (float) $request->collection_receivable_amount : null,
                    (string) $request->date,
                    (int) $id,
                    $basisOverride
                );
            }

            $odPayload = [
                    'status_date' => date('Y-m-d'),
                    'shipping_company_id' => $request->company_id,
                    'shippment_image' => $img_name,
                    'shipping_date' => $request->date,
                    'reviewed' => 0,
                ];
            if ($request->filled('collection_company_id')) {
                $odPayload['collection_company_id'] = (int) $request->collection_company_id;
            }
            if ($request->filled('collection_provider_type')) {
                $odPayload['collection_provider_type'] = $request->collection_provider_type;
                $odPayload['collection_provider_id'] = $request->filled('collection_provider_id')
                    ? (int) $request->collection_provider_id
                    : null;
            }
            if ($receivableSnapshot !== null) {
                $odPayload['shipping_receivable_amount'] = $receivableSnapshot['shipping_amount'];
                $odPayload['collection_receivable_amount'] = $receivableSnapshot['collection_amount'];
            }
            OrderDetails::updateOrCreate(
                ['order_id' => $id],
                $odPayload
            );

            $order->refresh()->load('order_details');
            app(OrderFinancialStateService::class)->inferCollectionProviderOnShip(
                $order,
                (int) $request->company_id,
                $request->filled('collection_company_id') ? (int) $request->collection_company_id : null,
                $request->input('collection_provider_type'),
                $request->filled('collection_provider_id') ? (int) $request->collection_provider_id : null,
            );

            if ($request->shippment_number) {
                OrderShippingNumber::create([
                    'order_id' => $id,
                    'shipment_number' => $request->shippment_number,
                    'user_id' => auth()->user()->id
                ]);
            }

            if ($request->has('note') && $request->note != '') {
                $note = $request->note;
                $added_from = 'شحن الطلب';
            $this->insertNote($order->id, $user_id, $note, $added_from, now());
            }

            if ($order->order_type == 'طلب صيانة') {
                $categories = DB::table('categories_balance')->where('ref', $id)->where('status', 'تم الصيانة')->get();
                if ($categories->count() > 0) {
                    DB::table('categories_balance')->where('ref', $id)->update(['status' => 'تم شحن']);
                    $order_products = OrderProduct::where('order_id', $id)->get();
                    foreach ($order_products as $op) {
                        if ($op->quantity > 0) {
                            $op->shipped_quantity = $op->quantity;
                            $op->save();
                        }
                    }
                }
            }


            if ($order->order_type != 'طلب صيانة' && ($order->customer_type != 'شركة' && ! in_array($order->order_type, self::ORDER_TYPES_INVENTORY_SHIP, true))) {
                $order_products = OrderProduct::where('order_id', $id)->get();
                /** @var InventoryMovementLedgerService $ledger */
                $ledger = app(InventoryMovementLedgerService::class);
                foreach ($order_products as $op) {
                    if ($op->quantity > 0) {
                        $cat = Category::find($op->category_id);
                        $ledger->recordOutbound(
                            $cat,
                            InventoryMovementType::SaleIssue,
                            (float) $op->quantity,
                            null,
                            null,
                            false,
                            'order_ship',
                            (int) $id,
                            'شحن طلب',
                            null,
                            auth()->user()->name ?? null
                        );
                        $op->shipped_quantity = $op->quantity;
                        $op->save();
                    }
                }
            }

            // Record courier shipping cost (Dr Shipping Expense, Cr Courier Payable/Cash).
            // This is SEPARATE from the customer — the customer already paid shipping
            // as part of their invoice. This records what WE pay the courier.
            $courierCost = $request->filled('courier_shipping_cost')
                ? (float) $request->courier_shipping_cost
                : (float) ($order->shipping_cost ?? 0);

            if ($courierCost > 0.0001 && $request->company_id) {
                $courierCo = ShippingCompany::find($request->company_id);
                if ($courierCo) {
                    $courierPaid = $request->boolean('courier_cost_paid', false);
                    app(ShippingCourierAccountingService::class)->recordShipmentCourierCost(
                        Order::findOrFail($id),
                        $courierCo,
                        $courierCost,
                        $courierPaid,
                        $request->input('courier_payment_type', 'bank'),
                        $request->input('courier_bank_id') ?: $request->input('bank_id'),
                        $request->input('courier_safe_id'),
                        $request->input('courier_service_account_id'),
                        $user_id
                    );
                }
            }

            DB::commit();
            return response()->json(['message' => 'success'], 200);
        } catch (UnlinkedReceivableAccountException $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function vip($id)
    {
        $order_details = OrderDetails::where('order_id', $id)->first();
        if (!$order_details) {
            return response()->json(['message' => 'not found'], 404);
        }
        $order_details->vip = !$order_details->vip;
        $order_details->save();
        return response()->json(['message' => 'success'], 200);
    }

    public function addShippmentNumber($id, Request $request)
    {
        OrderShippingNumber::create([
            'order_id' => $id,
            'shipment_number' => $request->value,
            'user_id' => auth()->user()->id
        ]);

        return response()->json(['message' => 'success'], 200);
    }

    public function addNote($id, Request $request)
    {
        $noteText = trim((string) ($request->input('value') ?? $request->query('value') ?? ''));
        if ($noteText === '') {
            return response()->json(['message' => 'يجب ادخال ملاحظة'], 422);
        }

        if (! Order::query()->whereKey($id)->exists()) {
            return response()->json(['message' => 'not found'], 404);
        }

        $user_id = auth()->user()->id;
        $added_from = 'تفاصيل الطلب';
        $this->insertNote($id, $user_id, $noteText, $added_from, now());

        return response()->json(['message' => 'success'], 200);
    }

    public function updateNote($id, Request $request)
    {
        $noteText = trim((string) ($request->input('value') ?? $request->query('value') ?? ''));
        if ($noteText === '') {
            return response()->json(['message' => 'يجب ادخال ملاحظة'], 422);
        }

        $note = Note::query()->find($id);
        if (! $note) {
            return response()->json(['message' => 'not found'], 404);
        }

        $authUser = auth()->user();
        if ($authUser->department !== 'Admin' && (int) $note->user_id !== (int) $authUser->id) {
            return response()->json(['message' => 'غير مصرح بتعديل هذه الملاحظة'], 403);
        }

        $note->update([
            'note' => $noteText,
            'edited_by_user_id' => $authUser->id,
        ]);

        return response()->json(['message' => 'success'], 200);
    }

    public function shortage($id)
    {
        $order_details = OrderDetails::where('order_id', $id)->first();
        if (!$order_details) {
            return response()->json(['message' => 'not found'], 404);
        }
        $order_details->shortage = !$order_details->shortage;
        $order_details->save();
        return response()->json(['message' => 'success shortage'], 200);
    }

    public function collect_order(Request $request, $id)
    {
        Order::reconcileCollectionStatusIfAllShippingLinesClosed((int) $id);

        $order = Order::with('order_details')->find($id);

        if (!$order) {
            return response()->json(['message' => 'not found'], 404);
        }

        if ($order->order_status === 'تم التحصيل') {
            return response()->json(['message' => 'تم تحصيل هذا الطلب مسبقاً'], 422);
        }

        $canCollect = in_array($order->order_status, ['تم شحن', 'تم التسليم'])
            && ($order->order_type != 'طلب صيانة' || $order->order_details->maintenance_date);
        if (!$canCollect) {
            return response()->json(['message' => ' حالة الطلب الحاليه ' . $order->order_status], 422);
        }

        /** @var \App\Services\Orders\OrderManualCollectionGuard $collectionGuard */
        $collectionGuard = app(\App\Services\Orders\OrderManualCollectionGuard::class);
        if (! $collectionGuard->allowsManualOrderCollection($order)) {
            return response()->json(['message' => $collectionGuard->manualCollectionBlockedMessage($order)], 422);
        }

        DB::beginTransaction();
        try {
            $request->merge(['id' => (int) $id]);
            $order->load('order_details');
            if ($order->order_details) {
                $this->ensureOpenShippingCollectionLinesForCollect($order, $order->order_details);
            }

            $collectBlockMessage = $collectionGuard->manualCollectionUnavailableMessage($order->fresh(['order_details']));
            if ($collectBlockMessage !== null) {
                throw new \InvalidArgumentException($collectBlockMessage);
            }

            $this->executeOrderCollection($request, (int) $id, null);

            DB::commit();
            return response()->json(['message' => 'success'], 200);
        } catch (\InvalidArgumentException $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * تحصيل جماعي لطلبات «تم شحن» — نفس قيود التحصيل الفردي (خزينة/بنك/حساب خدمي + إجراءات الشحن).
     *
     * @param  ?string  $preSavedReferenceImage  اسم ملف مسبق الرفع للتحصيل الجماعي (مرة واحدة لكل الطلبات)
     */
    public function bulk_collect_orders(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:orders,id',
            'shipping_company_id' => 'nullable|integer|exists:shipping_companies,id',
            'payment_type' => 'nullable|string|in:bank,safe,service_account',
            'bank_id' => 'nullable|integer',
            'safe_id' => 'nullable|integer',
            'service_account_id' => 'nullable|integer',
            'note' => 'nullable|string',
        ]);

        $orderIds = array_values(array_unique(array_map('intval', $request->order_ids)));

        $preSavedReferenceImage = null;
        if ($request->hasFile('reference_image')) {
            $img = $request->file('reference_image');
            $preSavedReferenceImage = 'bulk_' . uniqid('', true) . '.' . $img->getClientOriginalExtension();
            $img->move(public_path('images'), $preSavedReferenceImage);
        }

        DB::beginTransaction();
        try {
            $done = 0;
            foreach ($orderIds as $oid) {
                $order = Order::with('order_details')->find($oid);
                if (!$order) {
                    throw new \InvalidArgumentException('طلب غير موجود: ' . $oid);
                }
                $canCollect = in_array($order->order_status, ['تم شحن', 'تم التسليم'])
                    && ($order->order_type != 'طلب صيانة' || $order->order_details->maintenance_date);
                if (!$canCollect) {
                    throw new \InvalidArgumentException(
                        'الطلب رقم ' . $oid . ' ليس بحالة صالحة للتحصيل (الحالة: ' . $order->order_status . ')'
                    );
                }

                /** @var \App\Services\Orders\OrderManualCollectionGuard $collectionGuard */
                $collectionGuard = app(\App\Services\Orders\OrderManualCollectionGuard::class);
                if ($order->order_details) {
                    $this->ensureOpenShippingCollectionLinesForCollect($order, $order->order_details);
                }
                $collectBlockMessage = $collectionGuard->manualCollectionUnavailableMessage($order->fresh(['order_details']));
                if ($collectBlockMessage !== null) {
                    throw new \InvalidArgumentException('الطلب رقم ' . $oid . ': ' . $collectBlockMessage);
                }

                if ($request->filled('shipping_company_id')) {
                    $scid = (int) $request->shipping_company_id;
                    $linked = shippingCompanyDetails::where('order_id', $oid)
                        ->where('shipping_company_id', $scid)
                        ->whereIn('status', self::SHIPPING_DETAIL_OPEN_COLLECTION_STATUSES)
                        ->where('is_done', 0)
                        ->exists();
                    if (! $linked) {
                        throw new \InvalidArgumentException(
                            'الطلب ' . $oid . ' غير ظاهر كمستحق تحت شركة الشحن المحددة أو تم تحصيله.'
                        );
                    }
                }

                $this->executeOrderCollection($request, $oid, $preSavedReferenceImage);
                $done++;
            }

            DB::commit();

            return response()->json([
                'message' => 'success',
                'processed' => $done,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * منطق التحصيل الكامل لطلب واحد (محاسبة + رصيد شركات الشحن + مخزون إن وُجد).
     *
     * @param  ?string  $preSavedReferenceImage  عند التحصيل الجماعي يُمرَّر اسم ملف الإيصال المرفوع مرة واحدة
     */
    /**
     * POST /api/order/{id}/deliver
     */
    public function deliver_order(Request $request, $id)
    {
        $order = Order::with('order_details')->find($id);
        if (!$order) {
            return response()->json(['message' => 'not found'], 404);
        }

        $allowedStatuses = ['تم شحن', 'شحن جزئي'];
        if (!in_array($order->order_status, $allowedStatuses)) {
            return response()->json([
                'message' => ' حالة الطلب الحاليه ' . $order->order_status,
            ], 422);
        }

        DB::beginTransaction();
        try {
            $user_id = auth()->user()->id;

            $deliverySvc = app(DeliveryConfirmationAccountingService::class);
            $result = $deliverySvc->recordDeliveryReceivableTransfer($order);

            $od = $order->order_details;
            if ($od) {
                $od->delivery_date = now()->format('Y-m-d');
                $od->delivered_by_user_id = $user_id;
                $od->delivery_batch_code = $result['batch_code'];
                $od->status_date = now()->format('Y-m-d');
                $od->reviewed = 0;
                $od->save();
            }

            shippingCompanyDetails::where('order_id', $id)
                ->where('status', 'تم شحن')
                ->where('is_done', 0)
                ->update(['status' => 'تم التسليم']);

            $order->order_status = 'تم التسليم';
            $order->save();

            app(OrderFinancialStateService::class)->syncFromOrder($order->fresh(['order_details']));

            $this->insertTracking($order->id, 'تم التسليم', $user_id, now());

            if ($request->has('note') && $request->note != '') {
                $this->insertNote($order->id, $user_id, $request->note, 'تأكيد التسليم', now());
            }

            DB::commit();
            return response()->json(['message' => 'success'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/order/{id}/delivery-transfer-check
     *
     * Lightweight check: does delivering this order require a receivable
     * transfer from the customer to the shipping/collection company?
     * Returns { needs_transfer: bool } so the UI can decide whether to
     * show the "ستنتقل المديونية" warning.
     */
    public function delivery_transfer_check($id)
    {
        $order = Order::with('order_details.shipping_company', 'order_details.collection_company')->find($id);
        if (!$order) {
            return response()->json(['message' => 'not found'], 404);
        }

        $od = $order->order_details;
        if (!$od) {
            return response()->json(['needs_transfer' => false]);
        }

        $shippingCo = $od->shipping_company;
        $shippingReceivableAcc = app(\App\Services\Accounting\ReceivableTreeAccountGuard::class)
            ->sanitizeReceivableAccountId(
                $shippingCo?->receivable_tree_account_id ? (int) $shippingCo->receivable_tree_account_id : null
            );

        $collectionReceivableAcc = app(\App\Services\Shipping\CollectionReceivableAccountResolver::class)
            ->receivableAccountIdForOrderDetails($od);

        $hasIntermediaryAccount = $shippingReceivableAcc || $collectionReceivableAcc;
        if (!$hasIntermediaryAccount) {
            return response()->json(['needs_transfer' => false]);
        }

        $alreadyOnShipping = $shippingReceivableAcc
            ? round((float) \App\Models\AccountEntry::where('order_id', $order->id)
                ->where('entry_batch_code', 'like', 'ORD-' . $order->id . '-%')
                ->where('tree_account_id', $shippingReceivableAcc)
                ->sum('debit'), 2)
            : 0;

        $alreadyOnCollection = $collectionReceivableAcc
            ? round((float) \App\Models\AccountEntry::where('order_id', $order->id)
                ->where('entry_batch_code', 'like', 'ORD-' . $order->id . '-%')
                ->where('tree_account_id', $collectionReceivableAcc)
                ->sum('debit'), 2)
            : 0;

        $grandTotal = (float) $order->net_total;
        $totalAlreadyTransferred = $alreadyOnShipping + $alreadyOnCollection;

        $needsTransfer = $totalAlreadyTransferred < ($grandTotal - 0.01);

        return response()->json(['needs_transfer' => $needsTransfer]);
    }

    /**
     * POST /api/orders/bulk-deliver
     */
    public function bulk_deliver_orders(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:orders,id',
        ]);

        $orderIds = array_values(array_unique(array_map('intval', $request->order_ids)));

        DB::beginTransaction();
        try {
            $done = 0;
            $allowedStatuses = ['تم شحن', 'شحن جزئي'];
            foreach ($orderIds as $oid) {
                $order = Order::with('order_details')->find($oid);
                if (!$order || !in_array($order->order_status, $allowedStatuses)) {
                    continue;
                }

                $user_id = auth()->user()->id;

                $deliverySvc = app(DeliveryConfirmationAccountingService::class);
                $result = $deliverySvc->recordDeliveryReceivableTransfer($order);

                $od = $order->order_details;
                if ($od) {
                    $od->delivery_date = now()->format('Y-m-d');
                    $od->delivered_by_user_id = $user_id;
                    $od->delivery_batch_code = $result['batch_code'];
                    $od->status_date = now()->format('Y-m-d');
                    $od->reviewed = 0;
                    $od->save();
                }

                shippingCompanyDetails::where('order_id', $oid)
                    ->where('status', 'تم شحن')
                    ->where('is_done', 0)
                    ->update(['status' => 'تم التسليم']);

                $order->order_status = 'تم التسليم';
                $order->save();

                $this->insertTracking($order->id, 'تم التسليم', $user_id, now());
                $done++;
            }

            DB::commit();
            return response()->json(['message' => 'success', 'processed' => $done], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function executeOrderCollection(Request $request, int $id, ?string $preSavedReferenceImage): void
    {
        $request->merge(['id' => $id]);

        $user_id = auth()->user()->id;
        $order = Order::with('order_details')->find($id);
        if (!$order) {
            throw new \RuntimeException('not found');
        }

        $order_details = $order->order_details;
        $order_details->status_date = date('Y-m-d');
        $order_details->reviewed = 0;
        $order_details->save();

        if ($request->has('note') && $request->note != '') {
            $note = $request->note;
            $added_from = 'تحصيل الطلب';
            $this->insertNote($order->id, $user_id, $note, $added_from, now());
        }

        /** @var \App\Services\Orders\OrderManualCollectionGuard $collectionGuard */
        $collectionGuard = app(\App\Services\Orders\OrderManualCollectionGuard::class);

        $shippingDetails = $collectionGuard->filterManualCollectShippingRows(
            $order,
            shippingCompanyDetails::where('order_id', $id)
                ->whereIn('status', self::SHIPPING_DETAIL_OPEN_COLLECTION_STATUSES)
                ->where('is_done', 0)
                ->get()
        );
        $paymentType = $request->payment_type ?? 'bank';
        if ($order->customer_type == 'شركة') {
            if ($shippingDetails->isEmpty()
                && $collectionGuard->openCollectionReceivableAmount($order) <= 0.009
                && (float) $order->net_total > 0.0001) {
                throw new \RuntimeException('لا توجد بيانات شحن للتحصيل');
            }
            $collectFromCompanies = 0;
            foreach ($shippingDetails as $elm) {
                $collectFromCompanies += $elm->amount;
                $this->collectShippingCompanyDetailLine(
                    $elm,
                    $order,
                    $order_details,
                    $request,
                    $user_id,
                    $paymentType,
                );
            }

            $company = customerCompany::find($order->company_id);
            if ($company && $company->id) {
                $amount = (float) ($order->net_total - $collectFromCompanies);
                $details = ' تحصيل من عميل شركة ' . $company->name;
                $ref = $order->id;
                $type = 'الطلبات';

                $this->applyOrderCollectionToPaymentSource($order, $request, $amount, $user_id, $details, $ref, $type, $paymentType);

                $company_id = $order->company_id;
                $amount = number_format((float) -($order->net_total - $collectFromCompanies), 3, '.', '');
                $ref = $order->id;
                $details = ' تحصيل من طلب رقم ' . $order->id;
                $type = 'الطلبات';

                $bankIdForProc = ($paymentType === 'bank') ? $request->bank_id : null;

                DB::statement('CALL update_customer_company_balance(?, ?, ?, ?, ?, ?, ?, ?)', [
                    $company_id,
                    $amount,
                    $bankIdForProc,
                    $ref,
                    $details,
                    $type,
                    $user_id,
                    now(),
                ]);
            }
        } else {
            if ($order->order_type == 'طلب صيانة') {
                $orders = $collectionGuard->filterManualCollectShippingRows(
                    $order,
                    shippingCompanyDetails::where('order_id', $id)
                        ->whereIn('status', self::SHIPPING_DETAIL_OPEN_COLLECTION_STATUSES)
                        ->where('is_done', 0)
                        ->get()
                );
                $paymentType = $request->payment_type ?? 'bank';
                foreach ($orders as $elm) {
                    $this->collectShippingCompanyDetailLine(
                        $elm,
                        $order,
                        $order_details,
                        $request,
                        $user_id,
                        $paymentType,
                    );
                }
            } else {
                if ($shippingDetails->isEmpty()) {
                    if ($collectionGuard->openCollectionReceivableAmount($order) <= 0.009
                        && (float) $order->net_total > 0.0001) {
                        $collectBlockMessage = $collectionGuard->manualCollectionUnavailableMessage($order);
                        throw new \RuntimeException(
                            $collectBlockMessage ?? 'لا توجد بيانات شحن للطلب'
                        );
                    }
                } else {
                $paymentType = $request->payment_type ?? 'bank';
                foreach ($shippingDetails as $elm) {
                    $this->collectShippingCompanyDetailLine(
                        $elm,
                        $order,
                        $order_details,
                        $request,
                        $user_id,
                        $paymentType,
                    );
                }
                }

                if ($preSavedReferenceImage !== null) {
                    Order::where('id', $id)->update([
                        'reference_image' => $preSavedReferenceImage,
                        'reference_number' => $request->reference_number,
                    ]);
                } elseif ($request->has('reference_number') || $request->hasFile('reference_image')) {
                    $img_name = '';
                    if ($request->hasFile('reference_image')) {
                        $img = $request->file('reference_image');
                        $img_name = time() . '.' . $img->extension();
                        $img->move(public_path('images'), $img_name);
                    }

                    Order::where('id', $id)->update([
                        'reference_image' => $img_name,
                        'reference_number' => $request->reference_number,
                    ]);
                }
            }

            if ($request->receivedOrder) {
                $products = OrderProduct::where('order_id', $request->id)->get();
                /** @var InventoryMovementLedgerService $ledger */
                $ledger = app(InventoryMovementLedgerService::class);
                foreach ($products as $product) {
                    if ($product->quantity < 0) {
                        $cat = Category::where('id', $product->category_id)->first();
                        $q = abs((float) $product->quantity);
                        $avg = CategoryInventoryCostService::averageCostForCategoryIssue((int) $cat->id);
                        $ledger->recordInbound(
                            $cat,
                            InventoryMovementType::SaleIssue,
                            $q,
                            $avg,
                            $q * $avg,
                            false,
                            'order_collect',
                            (int) $request->id,
                            'تحصيل طلب — استلام',
                            null,
                            auth()->user()->name ?? null
                        );
                    }
                }
            } else {
                if ($request->has('reason') && $request->reason != '') {
                    $admin = User::where('department', 'admin')->first();
                    if (auth()->id() !== $admin->id) {
                        Notification::create([
                            'send_from' => auth()->id(),
                            'send_to' => $admin->id,
                            'type' => 'مرتجع',
                            'ref' => $request->id,
                            'order_id' => $request->id,
                            'note' => $request->reason,
                        ]);
                    }

                    Note::create([
                        'order_id' => $id,
                        'user_id' => auth()->user()->id,
                        'note' => $request->reason,
                        'added_from' => 'تحصيل الطلب',
                        'is_problem' => true,
                    ]);
                }
            }
        }

        $this->settleCollectionCompanyReceivableOnCollect(
            $order->fresh(['order_details']),
            $request,
            $user_id,
            $paymentType,
            $collectionGuard
        );

        $order = $order->fresh(['order_details']);
        $order_details = $order->order_details;
        if ($order_details && $order_details->shipping_receivable_amount !== null) {
            $order_details->shipping_receivable_amount = 0;
            $order_details->save();
        }

        if ($collectionGuard->shouldMarkOrderFullyCollected($order)) {
            if ($order_details) {
                $order_details->collection_date = date('Y-m-d');
                $order_details->save();
            }
            $order->order_status = 'تم التحصيل';
            $order->save();
            $this->insertTracking($order->id, 'تم تحصيل الطلب', $user_id, now());
        } else {
            $this->insertTracking(
                $order->id,
                'تحصيل جزء من الطلب — ما زالت هناك ذمة مفتوحة',
                $user_id,
                now()
            );
        }

        app(OrderFinancialStateService::class)->syncFromOrder($order->fresh(['order_details']));

        if ($order->order_type != 'طلب صيانة') {
            $products = OrderProduct::where('order_id', $order->id)->get();
            /** @var InventoryMovementLedgerService $ledger */
            $ledger = app(InventoryMovementLedgerService::class);
            foreach ($products as $product) {
                if ($product->quantity < 0) {
                    $cat = Category::where('id', $product->category_id)->first();
                    $q = abs((float) $product->quantity);
                    $avg = CategoryInventoryCostService::averageCostForCategoryIssue((int) $cat->id);
                    $ledger->recordInbound(
                        $cat,
                        InventoryMovementType::SaleIssue,
                        $q,
                        $avg,
                        $q * $avg,
                        false,
                        'order_collect',
                        (int) $order->id,
                        'تحصيل طلب',
                        null,
                        auth()->user()->name ?? null
                    );
                }
            }
        }
    }

    public function partCollect_order(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order) {
            return response()->json(['message' => 'not found'], 404);
        }

        if (!($order->customer_type == 'شركة' && in_array($order->order_status, ['طلب جديد', 'طلب مؤكد', 'شحن جزئي']))) {
            return response()->json(['message' => ' حالة الطلب الحاليه ' . $order->order_status], 422);
        }

        DB::beginTransaction();
        try {
            $user_id = auth()->user()->id;

            if ($order->customer_type == 'شركة' && ($order->order_status == 'طلب جديد' || $order->order_status == 'طلب مؤكد' || $order->order_status == 'شحن جزئي')) {
                $order->prepaid_amount = $order->prepaid_amount + $request->amount;
                $order->net_total = $order->net_total - $request->amount;
                $order->save();


                $company = CustomerCompany::find($order->company_id);

                $company_id = $order->company_id;
                $amount = (float)-$request->amount;
                $ref = $order->id;
                $details = ' تحصيل جزئي من طلب رقم ' . $order->id;
                $type = 'الطلبات';
                $paymentType = $request->payment_type ?? 'bank';
                $bankIdForProc = ($paymentType === 'bank') ? $request->bank_id : null;
                DB::statement('CALL update_customer_company_balance(?, ?, ?, ?, ?, ?, ?, ?)', [
                    $company_id,
                    $amount,
                    $bankIdForProc,
                    $ref,
                    $details,
                    $type,
                    $user_id,
                    now()
                ]);

                $amount = (float)$request->amount;
                $details = ' تحصيل جزئي من عميل شركة    ' . $company->name;
                $ref = $order->id;
                $type = 'الطلبات';
                $sourceName = '';
                if ($paymentType === 'safe' && $request->has('safe_id')) {
                    $safe = \App\Models\Safe::find($request->safe_id);
                    $sourceName = $safe ? $safe->name : 'خزينة';
                } elseif ($paymentType === 'service_account' && $request->has('service_account_id')) {
                    $svc = \App\Models\ServiceAccount::find($request->service_account_id);
                    $sourceName = $svc ? $svc->name : 'حساب خدمي';
                } else {
                    $bank = Bank::find($request->bank_id);
                    $sourceName = $bank ? $bank->name : 'بنك';
                }
                // رصيد الخزينة/البنك + القيود: OrderObserver (PARTCOLLECT) بعد تغيير prepaid_amount

                $action = ' تحصيل جزئي مبلغ ' . $request->amount . ' في حساب ' . $sourceName;
                $this->insertTracking($order->id, $action, $user_id, now());

                if ($request->has('note') && $request->note != '') {
                    $note = $request->note;
                    $added_from = 'تحصيل جزئي';
                    $this->insertNote($order->id, $user_id, $note, $added_from, now());
                }
            }

            DB::commit();
            return response()->json(['message' => 'success'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function revieworder(Request $request)
    {

        if ($request->has('orders')) {

            foreach ($request->orders as $order) {
                $order_details = OrderDetails::where('order_id', $order['id'])->first();
                $order_details->reviewed = 1;
                $order_details->save();
            }
        } else {
            $order_details = OrderDetails::where('order_id', $request->id)->first();
            if ($request->reviewd_note !== '') {
                $order_details->reviewed_note = $request->reviewd_note;
            }
            $order_details->reviewed = $request->reviewd;
            $order_details->save();

            $sentNotification = Notification::where('order_id', $request->id)
                ->where('type', 'مراجعة')->get();
            foreach ($sentNotification as $notification) {
                $notification->review_status = '2';
                $notification->save();
            }
        }

        return response()->json('success', 200);
    }

    public function userReviewOrder(Request $request)
    {

        $order_details = OrderDetails::where('order_id', $request->id)->first();
        if ($request->user_reviewed_note !== '') {
            $order_details->user_reviewed_note = $request->user_reviewed_note;
        }
        $order_details->user_reviewed = $request->user_reviewed;
        $order_details->save();

        $admin  = User::where('department', 'admin')->first();


        $sentNotification = Notification::where('send_from', $admin->id)
            ->where('send_to', auth()->id())
            ->where('order_id', $request->id)
            ->where('type', 'مراجعة')->latest()->first();

        $sentNotification->review_status = '1';
        $sentNotification->save();


        Notification::create([
            'send_from' =>  auth()->id(),
            'send_to' => $admin->id,
            'type' => 'مراجعة',
            'ref' => $request->id,
            'order_id' => $request->id,
            'note' => $request->user_reviewed_note,
            'review_status' => '1',
        ]);


        return response()->json('success', 200);
    }

    /**
     * مراجعة طلب Shopify: حفظ التعديلات (اختياري) + تسجيل المراجعة + نوت تلقائي بالتغييرات.
     */
    public function shopifyReview(int $id, Request $request, ShopifyOrderReviewApplyService $reviewService)
    {
        $order = Order::query()
            ->whereNotNull('shopify_order_id')
            ->findOrFail($id);

        $request->validate([
            'note' => 'nullable|string|max:2000',
            'order' => 'nullable|array',
            'order.customer_name' => 'nullable|string|max:255',
            'order.customer_phone_1' => 'nullable|string|max:50',
            'order.customer_phone_2' => 'nullable|string|max:50',
            'order.tel' => 'nullable|string|max:50',
            'order.governorate' => 'nullable|string|max:255',
            'order.city' => 'nullable|string|max:255',
            'order.address' => 'nullable|string|max:1000',
            'order.shipping_method_id' => 'nullable|integer|exists:shipping_methods,id',
            'order.shipping_cost' => 'nullable|numeric|min:0',
            'order.prepaid_amount' => 'nullable|numeric|min:0',
            'order.discount' => 'nullable|numeric|min:0',
            'order.total_invoice' => 'nullable|numeric|min:0',
            'order.net_total' => 'nullable|numeric',
            'order.vat' => 'nullable|numeric|min:0',
            'order.collect_note' => 'nullable|string|max:2000',
            'order_products' => 'nullable|array',
            'order_products.*.category_id' => 'required_with:order_products|integer|exists:categories,id',
            'order_products.*.quantity' => 'required_with:order_products|numeric|min:1',
            'order_products.*.price' => 'required_with:order_products|numeric|min:0',
            'order_products.*.special_details' => 'nullable|string|max:500',
        ]);

        $userId = auth()->id();
        if (! $userId) {
            return response()->json(['message' => 'غير مصرح'], 401);
        }

        if (! in_array($order->order_status, ['طلب جديد', 'طلب مؤكد', 'شحن جزئي'], true)) {
            return response()->json([
                'message' => 'لا يمكن مراجعة/تعديل طلب Shopify في حالة «'.$order->order_status.'».',
            ], 422);
        }

        $manualNote = $request->input('note');
        $manualNote = is_string($manualNote) && trim($manualNote) !== '' ? trim($manualNote) : null;

        $orderPayload = $request->input('order', []);
        $orderPayload = is_array($orderPayload) ? $orderPayload : [];

        $productsPayload = $request->input('order_products');
        $productsPayload = is_array($productsPayload) ? $productsPayload : null;

        try {
            $result = $reviewService->apply($order, $orderPayload, $productsPayload, $manualNote, (int) $userId);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['changes'] === []
                ? 'تم تسجيل مراجعة طلب Shopify (بدون تعديلات).'
                : 'تمت المراجعة وحفظ التعديلات.',
            'changes' => $result['changes'],
            'order' => $result['order'],
        ]);
    }

    public function userTempReviewOrder($id, Request $request)
    {
        OrderTempReview::create([
            'order_id' => $id,
            'user_id' => auth()->user()->id,
            'review' => $request->value,
        ]);

        if (auth()->user()->department == 'Admin') {
            return response()->json(['message' => 'success'], 200);
        }

        $admin  = User::where('department', 'Admin')->first();


        $sentNotification = Notification::where('send_from', $admin->id)
            ->where('send_to', auth()->id())
            ->where('order_id', $request->id)
            ->where('type', 'مراجعة مؤقتة')->latest()->first();

        $sentNotification->review_status = '1';
        $sentNotification->save();


        Notification::create([
            'send_from' =>  auth()->id(),
            'send_to' => $admin->id,
            'type' => 'مراجعة مؤقتة',
            'ref' => $id,
            'order_id' => $id,
            'note' => $request->value,
            'review_status' => '1',
        ]);

        return response()->json(['message' => 'success'], 200);
    }

    public function readTempReviewOrder($id)
    {
        Notification::where('order_id', $id)
            ->where('type', 'مراجعة مؤقتة')
            ->update(['review_status' => 2]);

        return response()->json(['message' => 'success'], 200);
    }

    public function maintained(Request $request, $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json(['message' => 'not found'], 404);
        }

        if (!($order->order_status == 'تم الاستلام')) {
            return response()->json(['message' => ' حالة الطلب الحاليه ' . $order->order_status], 422);
        }

        DB::beginTransaction();
        try {
            $order->order_status = 'تم الصيانة';
            $order->net_total = $order->net_total + $request->maintenance_cost;
            $order->total_invoice = $order->total_invoice + $request->maintenance_cost;
            $order->save();

            $action = 'تم صيانة الطلب';
            $this->insertTracking($order->id, $action, auth()->user()->id, now());

            $order_details = OrderDetails::where('order_id', $id)->first();
            $order_details->maintenance_cost = $request->maintenance_cost;
            $order_details->maintenance_date = date('Y-m-d');
            $order_details->reviewed = 0;
            $order_details->status_date = date('Y-m-d');
            $order_details->save();
            DB::table('categories_balance')->where('ref', $id)->update(['status' => 'تم الصيانة']);

            DB::commit();
            return response()->json(['message' => 'success'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function received($id, Request $request)
    {

        $order = Order::with('order_details')->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if (!($order->order_status == 'تم شحن' && $order->order_type == 'طلب صيانة' && !$order->order_details->maintenance_date)) {
            return response()->json(['message' => ' حالة الطلب الحاليه ' . $order->order_status], 422);
        }


        DB::beginTransaction();
        try {
            $user_id = auth()->user()->id;
            $order->order_status = 'تم الاستلام';

            $action = 'تم استلام الطلب';
            $this->insertTracking($order->id, $action, $user_id, now());

            $order_details = OrderDetails::where('order_id', $id)->first();
            $order_details->receiving_date = date('Y-m-d');
            $order_details->status_date = date('Y-m-d');
            $order_details->reviewed = 0;
            $order_products = OrderProduct::where('order_id', $id)->get();

            OrderMaintenReason::create([
                'order_id' => $id,
                'order_status' => $order->order_status,
                'mainten_reason' => $request->maintenReason,
            ]);

            foreach ($order_products as $op) {
                $category = Category::find($op->category_id);

                $isExist = Category::where('category_name', $category->category_name)->where('warehouse', 'مخزن صيانة')->first();

                if (!$isExist) {
                    $isExist = Category::create([
                        'category_name' => $category->category_name,
                        'category_price' => $category->category_price,
                        'unit_price' => (float) ($category->unit_price ?? $category->category_price),
                        'quantity' => 0,
                        'minimum_quantity' => 0,
                        'initial_balance' => 0,
                        'warehouse' => 'مخزن صيانة',
                        'production_id' => $category->production_id,
                        'measurement_id' => $category->measurement_id,
                        'category_image' => $category->category_image,
                    ]);
                }
                DB::table('categories_balance')->insert([
                    'invoice_number' => 0,
                    'category_id' => $isExist->id,
                    'type' => 'صيانة',
                    'quantity' => $op->quantity,
                    'balance_before' => 0,
                    'balance_after' => 0,
                    'price' => 0,
                    'total_price' => 0,
                    'unit_cost' => 0,
                    'cost_total' => 0,
                    'created_at' => now(),
                    'ref' => $id,
                    'by' => auth()->user()->name,
                    'status' => 'لم يتم الصيانة',
                ]);
            }

            if ($request->bank != 'null') {
                $order->prepaid_amount = $order->net_total;
                $firstShip = ShippingCompanyDetails::where('order_id', $id)->first();
                $firstShip->is_done = 1;
                $firstShip->save();

                $shippingCompany = ShippingCompany::find($order_details->shipping_company_id);

                $shipping_company_id = (float)$order_details->shipping_company_id;
                $order_id = $id;
                $shipping_date = $order_details->shipping_date;
                $status = 'تم التحصيل';
                $amount = (float)-$order->net_total;
                DB::statement('CALL shipping_company_procedure(?, ?, ?, ?, ?, ?, ?)', [
                    $shipping_company_id,
                    $order_id,
                    $shipping_date,
                    $status,
                    $amount,
                    auth()->user()->name,
                    now()
                ]);

                $amount = (float)($order->net_total);
                $details = ' تحصيل من شركة شحن ' . $shippingCompany->name . ' مصاريف شحن اوردر للصيانة ';
                $ref = $id;
                $type = 'الطلبات';
                $this->updateBankBalance($request->bank, $amount, $order->id, $user_id, $details, $ref, $type, now());

                $order->net_total = 0;
            } else {
                if ($order->net_total > 0) {
                    $admin  = User::where('department', 'admin')->first();

                    if (auth()->id() !== $admin->id) {
                        Notification::create([
                            'send_from' =>  auth()->id(),
                            'send_to' => $admin->id,
                            'type' => 'مرتجع',
                            'ref' => $id,
                            'order_id' => $id,
                            'note' => 'لم يتم استلام مصاريف الشحن' . $request->reason,
                        ]);
                    }

                    Note::create([
                        'order_id' => $id,
                        'user_id' => auth()->user()->id,
                        'note' => 'لم يتم استلام مصاريف الشحن' . $request->reason,
                        'added_from' => 'استلام اوردر صيانة',
                        'is_problem' => true
                    ]);
                }
            }
            $order->save();
            $order_details->save();
            DB::commit();
            return response()->json(['message' => 'success'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function phoneNumbers()
    {
        $results = DB::table('orders')
            ->select(
                'customer_phone_1',
                DB::raw('MIN(customer_phone_2) AS customer_phone_2'),
                DB::raw('MIN(tel) AS tel'),
                DB::raw('MIN(customer_name) AS customer_name'),
                DB::raw('MIN(governorate) AS governorate'),
                DB::raw('MIN(city) AS city'),
                DB::raw('MIN(address) AS address')
            )
            ->groupBy('customer_phone_1')
            ->get();

        return response()->json($results, 200);
    }

    public function getOrdersNumbers()
    {
        $results = DB::table('orders')
            ->select('id')
            ->get();

        return response()->json($results, 200);
    }



    public function search(Request $request){

        $itemsPerPage = request('itemsPerPage') ?: 10;

        $user = auth()->user();
        $userDepartment = $user->department;
        $userId = $user->id;

        $privateOrder = $userDepartment == 'Admin' ? 1 : null;

        $order = Order::query();

        if ($request->has('company_id')) {
            $order->where('company_id',$request->company_id);
        }

        if(!$privateOrder){
            $order->where(function ($query) use ($userId) {
                $query->where('private_order', null)
                    ->orWhereHas('notifications', function ($notificationQuery) use ($userId) {
                        $notificationQuery->where('send_to', $userId);
                    });
            });
        }

        if ($request->has('private_order')) {
            if($request->private_order == 'null'){
                $order->where('private_order', null);
            } else if($request->private_order == '1'){
                $order->where('private_order', 1);
            }
        }

        $this->orderStatusVisibility->applySearchScope($order, $user);

        if ($request->has('prepaidAmount') && $request->prepaidAmount != '') {
            if (!$request->has('paid') || $request->paid == '') {
                $order->where('prepaid_amount', '>', 0)
                    ->where('net_total', '>', 0);
            }
        }

        if ($request->has('paid') && $request->paid != '') {
            $order->where('net_total', '=', 0);
        }

        if ($request->has('collectType') && $request->collectType != '') {
            if ($request->collectType === 'تحصيل متغير') {
                $order->where(function($query) use ($request) {
                    $query->whereNotNull('collect_note')
                            ->orWhereNotNull('reference_number')
                            ->orWhereNotNull('reference_image');
                });
            } elseif ($request->collectType === 'تحصيل الكتروني') {
                $order->where(function($query) use ($request) {
                    $query->whereNotNull('reference_number')
                            ->orWhereNotNull('reference_image');
                });
            }
        }

        if($request->has('customer_type')&&$request->customer_type !=''){
            $order->where('customer_type',$request->customer_type );
        }

        if($request->has('order_date')&&$request->order_date !=''){
            $order->where('order_date',$request->order_date );
        }

        if($request->has('delivery_date')&&$request->delivery_date !=''){
            $order->where('delivery_date', '<=',$request->delivery_date );
        }

        if($request->has('order_type')&&$request->order_type!=''){
            $order->where('order_type',$request->order_type);
        }
        if($request->has('shipping_company_id')&&$request->shipping_company_id!=''){
            $order->whereHas('order_details',function($q) use($request){
                $q->where('shipping_company_id',$request->shipping_company_id);
            });
        }

        if($request->has('category_id')&&$request->category_id!=''){
            $order->whereHas('order_products',function($q) use($request){
                $q->where('category_id',$request->category_id);
            });
        }

        if($request->has('need_by_date')&&$request->need_by_date!=''){
            $order->whereHas('order_details',function($q) use($request){
                $q->where('need_by_date',$request->need_by_date);
            });
        }

        if ($request->has('status_date') && $request->status_date != '') {
            $order->whereHas('order_details', function ($q) use ($request) {
                $q->where('status_date', $request->status_date);
            });
        }

        if($request->has('vip') && $request->vip !=''){
            $order->whereHas('order_details',function($q) use($request){
                $q->where('vip',$request->vip);
            });
        }

        if ($request->has('confimedOrderNotifi') && $request->confimedOrderNotifi) {
            $order->where('order_status', 'طلب مؤكد')
                ->whereHas('order_details', function($q) {
                    $q->where('status_date', '<', Carbon::now()->subDays(3));
                });
        }

        if($request->has('reviewed') && $request->reviewed !=''){
            $order->whereHas('order_details',function($q) use($request){
                if($request->reviewed == "2"){
                    $q->where('reviewed', 1)
                    ->whereNotNull('reviewed_note');
                } else{
                    $q->where('reviewed',$request->reviewed);
                }

            });
        }


        if($request->has('shortage') && $request->shortage !=''){
            $order->whereHas('order_details',function($q) use($request){
                $q->where('shortage',$request->shortage);
            });
        }


        if($request->has('shippment_number') && $request->shippment_number !=''){
            $order->whereHas('order_shipment_number',function($q) use($request){
                $q->where('shipment_number','like','%'.$request->shippment_number.'%');
            });
        }

        if($request->has('governorate')&&$request->governorate!=''){
            $order->where('governorate','like','%'.$request->governorate.'%');
        }
        if($request->has('city')&&$request->city!=''){
            $order->where('city','like','%'.$request->city.'%');
        }



        if($request->has('customer_name')&&$request->customer_name!=''){
            $order->where('customer_name','like','%'.$request->customer_name.'%');
        }
        if($request->has('customer_phone')&&$request->customer_phone!=''){
            $order->where('customer_phone_1','like','%'.$request->customer_phone.'%');
        }
        if($request->has('order_number')&&$request->order_number!=''){
            $order->where('id','like','%'.$request->order_number.'%');
        }

        if($request->has('order_status')&&$request->order_status!=''){
            $order->where('order_status',$request->order_status);
        }
        if($request->has('order_source_id')&&$request->order_source_id!=''){
            $order->where('order_source_id',$request->order_source_id);
        }
        if($request->has('shipping_method_id')&&$request->shipping_method_id!=''){
            $order->where('shipping_method_id',$request->shipping_method_id);
        }
        if($request->has('shipping_line_id')&&$request->shipping_line_id!=''){
            $order->whereHas('order_details',function($q) use($request){
                $q->where('shipping_line_id',$request->shipping_line_id);
            });
        }

        if ($request->has('shopify') && $request->shopify !== '') {
            if ($request->shopify === '1') {
                $order->whereNotNull('shopify_order_id');
            } elseif ($request->shopify === 'pending_review') {
                $order->whereNotNull('shopify_order_id')->whereNull('shopify_reviewed_at');
            } elseif ($request->shopify === 'reviewed') {
                $order->whereNotNull('shopify_order_id')->whereNotNull('shopify_reviewed_at');
            }
        }

        $orders = $order->with([
            'order_details.shipping_line',
            'order_details.shipping_company',
            'shipping_method',
            'shopifyReviewer:id,name',
            'order_products.category:id,category_name',
            'notifications:id,send_from,send_to,type,ref,note,order_id,notification_number,created_at,is_read',
            'notifications.sender:id,name',
            'notifications' => function ($query) {
                $query->where('send_to', auth()->id());
            }
        ])->select('orders.*', DB::raw('(SELECT COUNT(*) FROM orders AS o WHERE o.customer_phone_1 = orders.customer_phone_1) AS customer_orders_count'))
        ->withCount([
            'notifications as review_notifications_count' => function ($query) {
                $query->where('type', 'مراجعة')
                    ->where('send_from', auth()->id());
            },])
        ->orderBy('id', 'desc')
        ->paginate($itemsPerPage);
        return response()->json($orders, 200);
    }



  public function allUserUnique(Request $request)
{
    $itemsPerPage = $request->get('itemsPerPage', 10);

    $query = Order::query()
        ->select(
            'customer_phone_1',
            DB::raw('MAX(customer_name) as customer_name'),
            DB::raw("MAX(COALESCE(customer_type, 'فرد')) as customer_type"),
            DB::raw('MAX(governorate) as governorate'),
            DB::raw('MAX(city) as city'),
            DB::raw('COUNT(orders.id) as orders_count'),
            DB::raw('MAX(company_id) as resolved_company_id'),
            // أرقام تشغيلية من الطلبات (احتياطي إن لم يُوجد حساب في الشجرة)
            DB::raw('SUM(net_total) as orders_net_total_sum'),
            DB::raw('SUM(prepaid_amount) as orders_prepaid_sum'),
            DB::raw('SUM(COALESCE(shipping_cost, 0)) as total_shipping')
        )
        ->groupBy('customer_phone_1')
        ->orderByDesc(DB::raw('MAX(orders.id)'));

    if ($request->filled('search')) {
        $term = '%' . addcslashes($request->input('search'), '%_\\') . '%';
        $query->where(function ($q) use ($term) {
            $q->where('customer_name', 'like', $term)
                ->orWhere('customer_phone_1', 'like', $term);
        });
    }

    if ($request->filled('from_date')) {
        $query->where('order_date', '>=', $request->input('from_date'));
    }
    if ($request->filled('to_date')) {
        $query->where('order_date', '<=', $request->input('to_date'));
    }

    $customers = $query->paginate($itemsPerPage);

    $linking = app(AccountLinkingService::class);
    $customers->getCollection()->transform(function ($row) use ($linking) {
        $companyId = isset($row->resolved_company_id) && $row->resolved_company_id !== null
            ? (int) $row->resolved_company_id
            : null;

        $tree = $linking->findExistingCustomerTreeAccount(
            (string) ($row->customer_type ?? 'فرد'),
            (string) ($row->customer_name ?? ''),
            $row->customer_phone_1,
            $companyId
        );

        if ($tree) {
            // مطابقة شجرة الحسابات: عمودا مدين/دائن كما في البطاقة
            $row->total_debit = round((float) ($tree->debit_balance ?? 0), 2);
            $row->total_credit = round((float) ($tree->credit_balance ?? 0), 2);
        } else {
            $row->total_debit = round((float) ($row->orders_net_total_sum ?? 0), 2);
            $row->total_credit = round((float) ($row->orders_prepaid_sum ?? 0), 2);
        }

        unset($row->orders_net_total_sum, $row->orders_prepaid_sum, $row->resolved_company_id);

        return $row;
    });

    return response()->json($customers);
}


    private function insertTracking($order_id, $action, $user_id, $created_at)
    {
        DB::table('trackings')->insert([
            'order_id' => $order_id,
            'date' => \Carbon\Carbon::parse($created_at)->toDateString(),
            'action' => $action,
            'user_id' => $user_id,
            'created_at' => $created_at,
            'updated_at' => $created_at
        ]);
    }

    private function userCanManageOrderPrepaid(?User $user = null): bool
    {
        $user ??= auth()->user();
        if (! $user instanceof User) {
            return false;
        }

        if (trim((string) ($user->department ?? '')) === 'Admin') {
            return true;
        }

        return has_any_permission([
            'finance.account_statement.edit',
            'orders.edit',
            'system.rbac',
        ]);
    }

    private function insertNote($order_id, $user_id, $note, $added_from, $created_at)
    {
        DB::table('notes')->insert([
            'order_id' => $order_id,
            'user_id' => $user_id,
            'note' => $note,
            'added_from' => $added_from,
            'created_at' => $created_at,
            'updated_at' => $created_at
        ]);
    }

    /**
     * إرجاع مبلغ تحت الحساب للعميل (أفراد/شركات) — نفس منطق الإلغاء.
     */
    private function refundPrepaidUnderAccount(Order $order, Request $request, int $orderId, int $userId, string $reason): void
    {
        $prepaid = round((float) ($order->prepaid_amount ?? 0), 3);
        if ($prepaid <= 0.0001 || ! $request->has('moneyReturnedStatus')) {
            return;
        }

        $ref = $orderId;
        $type = 'الطلبات';
        $details = $reason.' — طلب رقم '.$orderId;

        if ($order->customer_type === 'افراد') {
            $amount = (float) -$prepaid;
            if ($request->moneyReturnedStatus === 'approved' && $request->moneyReturnedBank) {
                $this->updateBankBalance((int) $request->moneyReturnedBank, $amount, $orderId, $userId, $details, $ref, $type, now());
            } elseif ($request->moneyReturnedStatus === 'pending') {
                PendingBankBalance::create([
                    'amount' => $amount,
                    'details' => $details,
                    'ref' => $ref,
                    'type' => $type,
                    'bank_id' => $order->bank_id,
                    'user_id' => $userId,
                ]);
            }

            return;
        }

        if ($order->customer_type === 'شركة' && $order->company_id) {
            $company_id = $order->company_id;
            $amount = (float) $prepaid;
            DB::statement('CALL update_customer_company_balance(?, ?, ?, ?, ?, ?, ?, ?)', [
                $company_id,
                $amount,
                null,
                $ref,
                $details,
                $type,
                $userId,
                now(),
            ]);

            if ($request->moneyReturnedStatus === 'approved' && $request->moneyReturnedBank) {
                $bank_id = (int) $request->moneyReturnedBank;
                $this->updateBankBalance($bank_id, -$prepaid, $orderId, $userId, $details, $ref, $type, now());
                $bankName = Bank::find($bank_id);
                $action = ' إرجاع مبلغ تحت الحساب '.$prepaid.' من خزينة '.($bankName->name ?? $bank_id).' عند تجديد الطلب ';
                $this->insertTracking($orderId, $action, $userId, now());
            } elseif ($request->moneyReturnedStatus === 'pending' && $order->bank_id) {
                PendingBankBalance::create([
                    'amount' => (float) -$prepaid,
                    'details' => $details,
                    'ref' => $ref,
                    'type' => $type,
                    'bank_id' => $order->bank_id,
                    'user_id' => $userId,
                ]);
            }
        }
    }

    /**
     * تسجيل دفعة مقدمة جديدة بعد التجديد (بنك/خزينة/حساب خدمي/معلق/شركة تحصيل).
     */
    private function applyRenewPrepaid(
        Order $order,
        OrderDetails $orderDetails,
        Request $request,
        float $renewAmount,
        int $userId
    ): void {
        $paymentType = (string) $request->input('renewPaymentType', '');
        if ($paymentType === '' && $request->filled('renewBankId')) {
            $paymentType = 'bank';
        }
        if (! in_array($paymentType, ['bank', 'safe', 'service_account', 'pending', 'collection_company'], true)) {
            throw new \InvalidArgumentException('مصدر الدفع غير صالح للدفعة الجديدة');
        }

        $order->net_total = round((float) $order->net_total - $renewAmount, 3);
        $order->prepaid_amount = $renewAmount;
        $order->prepaid_payment_type = $paymentType;
        $order->bank_id = null;

        $details = 'مبلغ تحت الحساب من تجديد الطلب';
        $ref = $order->id;
        $type = 'الطلبات';
        $sourceLabel = 'بانتظار تسجيل المصدر';
        $companyBalanceBankId = null;

        if ($paymentType === 'bank') {
            $bankId = (int) $request->input('renewBankId');
            $bank = Bank::find($bankId);
            if (! $bank) {
                throw new \InvalidArgumentException('يجب اختيار بنك صالح للدفعة الجديدة');
            }
            $order->bank_id = $bankId;
            $companyBalanceBankId = $bankId;
            $this->updateBankBalance($bankId, $renewAmount, $order->id, $userId, $details, $ref, $type, now());
            $sourceLabel = $bank->name ?? 'بنك';
        } elseif ($paymentType === 'safe') {
            $safeId = (int) $request->input('renewSafeId');
            $safe = \App\Models\Safe::find($safeId);
            if (! $safe) {
                throw new \InvalidArgumentException('يجب اختيار خزينة صالحة للدفعة الجديدة');
            }
            $this->updateSafeBalance($safeId, $renewAmount, $order->id, $userId, $details, $ref, $type, now());
            $sourceLabel = $safe->name ?? 'خزينة';
        } elseif ($paymentType === 'service_account') {
            $serviceAccountId = (int) $request->input('renewServiceAccountId');
            $serviceAccount = \App\Models\ServiceAccount::find($serviceAccountId);
            if (! $serviceAccount) {
                throw new \InvalidArgumentException('يجب اختيار حساب خدمي صالح للدفعة الجديدة');
            }
            $this->updateServiceAccountBalance($serviceAccountId, $renewAmount, $order->id, $userId, $details, $ref, $type, now());
            $sourceLabel = $serviceAccount->name ?? 'حساب خدمي';
        } elseif ($paymentType === 'collection_company') {
            $companyId = (int) $request->input('renewCollectionCompanyId');
            $company = CollectionCompany::find($companyId);
            if (! $company) {
                throw new \InvalidArgumentException('يجب اختيار شركة تحصيل صالحة');
            }

            $orderDetails->collection_provider_type = CollectionProviderType::CollectionCompany->value;
            $orderDetails->collection_provider_id = $company->id;
            if ($company->linked_shipping_company_id) {
                $orderDetails->collection_company_id = (int) $company->linked_shipping_company_id;
            }
            $orderDetails->collection_receivable_amount = round($renewAmount, 3);
            $orderDetails->collection_status = OrderCollectionStatus::Pending->value;
            $orderDetails->settlement_status = OrderSettlementStatus::Open->value;
            $sourceLabel = $company->name;
        }

        if ($paymentType === 'collection_company') {
            $action = ' مبلغ تحت الحساب '.$renewAmount.' على شركة تحصيل «'.$sourceLabel.'» من تجديد الطلب ';
        } elseif ($paymentType === 'pending') {
            $action = ' مبلغ تحت الحساب '.$renewAmount.' — بانتظار تسجيل مصدر الدفع من تجديد الطلب ';
        } else {
            $action = ' مبلغ تحت الحساب '.$renewAmount.' في '.$sourceLabel.' من تجديد الطلب ';
        }
        $this->insertTracking($order->id, $action, $userId, now());

        if ($order->customer_type === 'شركة' && $order->company_id && in_array($paymentType, ['bank', 'safe', 'service_account'], true)) {
            DB::statement('CALL update_customer_company_balance(?, ?, ?, ?, ?, ?, ?, ?)', [
                $order->company_id,
                (float) -$renewAmount,
                $companyBalanceBankId,
                $ref,
                'مبلغ تحت الحساب من طلب رقم '.$order->id.' من تجديد الطلب ',
                $type,
                $userId,
                now(),
            ]);
        }
    }

    /**
     * عكس قيود نقل الذمة عند رفض الاستلام بعد «تم التسليم».
     */
    private function reverseDeliveryAccountingIfNeeded(Order $order, ?OrderDetails $orderDetails): void
    {
        if ($order->order_status !== 'تم التسليم' || ! $orderDetails) {
            return;
        }

        app(DeliveryConfirmationAccountingService::class)->reverseDeliveryTransfer($order);

        $orderDetails->delivery_batch_code = null;
        $orderDetails->liability_transferred_at = null;
        $orderDetails->liability_holder_type = null;
        $orderDetails->liability_holder_id = null;
    }

    /**
     * إعادة ضبط حقول التنفيذ/التحصيل عند تجديد الطلب.
     */
    private function resetOrderDetailsForRenew(OrderDetails $orderDetails, bool $preservePrepaidCollection = false): void
    {
        $orderDetails->shipping_date = null;
        $orderDetails->delivery_date = null;
        $orderDetails->collection_date = null;
        $orderDetails->liability_transferred_at = null;
        $orderDetails->liability_holder_type = null;
        $orderDetails->liability_holder_id = null;
        $orderDetails->delivery_status = OrderDeliveryStatus::Pending->value;

        if (! $preservePrepaidCollection) {
            $orderDetails->collection_provider_type = CollectionProviderType::None->value;
            $orderDetails->collection_provider_id = null;
            $orderDetails->collection_status = OrderCollectionStatus::Pending->value;
            $orderDetails->settlement_status = OrderSettlementStatus::Open->value;
            $orderDetails->collection_receivable_amount = null;
        }
    }

    private function updateBankBalance($bank_id, $amount, $order_id, $user_id, $details, $ref, $type, $created_at, ?int $creditReceivableAccountId = null)
    {
        $bank = Bank::find($bank_id);
        $order = Order::find($order_id);
        if (! $bank || ! $order) {
            return;
        }

        app(\App\Services\Accounting\OrderPaymentSourceLedgerService::class)->recordBankMovement(
            $bank,
            (float) $amount,
            $order,
            $details,
            (string) $ref,
            $type,
            (int) $user_id,
            \Carbon\Carbon::parse($created_at)->toDateString(),
            $creditReceivableAccountId,
        );
    }

private function updateSafeBalance($safe_id, $amount, $order_id, $user_id, $details, $ref, $type, $created_at, ?int $creditReceivableAccountId = null)
{
    $safe = \App\Models\Safe::find($safe_id);
    $order = Order::find($order_id);
    if (! $safe || ! $order) {
        return;
    }

    app(\App\Services\Accounting\OrderPaymentSourceLedgerService::class)->recordSafeMovement(
        $safe,
        (float) $amount,
        $order,
        $details,
        (string) $ref,
        $type,
        (int) $user_id,
        \Carbon\Carbon::parse($created_at)->toDateString(),
        $creditReceivableAccountId,
    );
}
    private function updateServiceAccountBalance($service_account_id, $amount, $order_id, $user_id, $details, $ref, $type, $created_at, ?int $creditReceivableAccountId = null)
    {
        $account = \App\Models\ServiceAccount::find($service_account_id);
        $order = Order::find($order_id);
        if (! $account || ! $order) {
            return;
        }

        app(\App\Services\Accounting\OrderPaymentSourceLedgerService::class)->recordServiceAccountMovement(
            $account,
            (float) $amount,
            $order,
            $details,
            (string) $ref,
            $type,
            (int) $user_id,
            \Carbon\Carbon::parse($created_at)->toDateString(),
            $creditReceivableAccountId,
        );
    }

    /**
     * @return array{type: string, bank_id: int|null, source_label: string|null}
     */
    private function resolvePrepaidPaymentFromRequest(Request $request): array
    {
        $prepaid = (float) ($request->prepaid_amount ?? 0);
        if ($prepaid <= 0.0001) {
            return ['type' => 'none', 'bank_id' => null, 'source_label' => null];
        }

        $paymentType = (string) ($request->input('payment_type', 'bank'));
        if ($paymentType === 'pending') {
            return ['type' => 'pending', 'bank_id' => null, 'source_label' => null];
        }

        if ($paymentType === 'safe' && $request->filled('safe_id')) {
            $safe = \App\Models\Safe::find($request->safe_id);

            return [
                'type' => 'safe',
                'bank_id' => null,
                'source_label' => $safe?->name ?? 'خزينة',
            ];
        }

        if ($paymentType === 'service_account' && $request->filled('service_account_id')) {
            $svc = \App\Models\ServiceAccount::find($request->service_account_id);

            return [
                'type' => 'service_account',
                'bank_id' => null,
                'source_label' => $svc?->name ?? 'حساب خدمي',
            ];
        }

        $bankId = $request->input('bank') ?? $request->input('bank_id');
        if ($bankId !== null && $bankId !== '' && $bankId !== 'null') {
            $bank = Bank::find((int) $bankId);

            return [
                'type' => 'bank',
                'bank_id' => (int) $bankId,
                'source_label' => $bank?->name ?? 'بنك',
            ];
        }

        return ['type' => 'pending', 'bank_id' => null, 'source_label' => null];
    }

    /**
     * تحديث رصيد البنك/الخزينة/الحساب الخدمي + قيود ذمم العملاء (أفراد أو شركات).
     */
    /**
     * تسجيل رصيد شركة الشحن و/أو شركة التحصيل عند «تم شحن» (shipping_company_procedure لكل جزء).
     *
     * @return array{shipping_amount: float, collection_amount: float}
     */
    /**
     * يتحقق قبل الشحن من ربط جهات التحصيل/الشحن بحساب ذمم في شجرة الحسابات،
     * حتى تنتقل مديونية العميل بشكل صحيح عند التسليم. يرمي
     * {@see UnlinkedReceivableAccountException} عند وجود جهة غير مرتبطة.
     */
    private function assertReceivableAccountsLinked(Order $order, Request $request, ?float $basisOverride): void
    {
        app(ShipmentReceivableAccountGuard::class)->assertLinkedForShip(
            $order,
            (int) $request->company_id,
            $request->input('collection_provider_type'),
            $request->filled('collection_provider_id') ? (int) $request->collection_provider_id : null,
            $request->filled('collection_company_id') ? (int) $request->collection_company_id : null,
            $request->filled('shipping_receivable_amount') ? (float) $request->shipping_receivable_amount : null,
            $request->filled('collection_receivable_amount') ? (float) $request->collection_receivable_amount : null,
            $basisOverride,
        );
    }

    /**
     * إنشاء سطور shipping_company_details المفقودة (مثلاً بعد فشل الإجراء المخزن عند الشحن).
     */
    private function ensureOpenShippingCollectionLinesForCollect(Order $order, OrderDetails $orderDetails): void
    {
        $orderId = (int) $order->id;

        /** @var \App\Services\Orders\OrderManualCollectionGuard $collectionGuard */
        $collectionGuard = app(\App\Services\Orders\OrderManualCollectionGuard::class);
        if ($collectionGuard->openManualCollectShippingRows($order)->isNotEmpty()) {
            return;
        }

        if (! $collectionGuard->allowsManualShippingCollection($order)) {
            return;
        }

        if (shippingCompanyDetails::where('order_id', $orderId)->exists()) {
            return;
        }

        $net = round((float) ($order->net_total ?? 0), 3);
        if ($net <= 0.0001) {
            return;
        }

        $courierId = (int) ($orderDetails->shipping_company_id ?? 0);
        if ($courierId <= 0) {
            throw new \RuntimeException('لا توجد شركة شحن مرتبطة بالطلب — لا يمكن إنشاء مستحقات التحصيل');
        }

        $collectionCompanyId = $orderDetails->collection_company_id
            ? (int) $orderDetails->collection_company_id
            : null;
        $shippingDate = $orderDetails->shipping_date
            ? (string) $orderDetails->shipping_date
            : now()->format('Y-m-d');

        $manualShip = $orderDetails->shipping_receivable_amount !== null
            ? (float) $orderDetails->shipping_receivable_amount
            : null;
        $manualColl = $orderDetails->collection_receivable_amount !== null
            ? (float) $orderDetails->collection_receivable_amount
            : null;

        $snapshot = $this->postShippedReceivableSegments(
            $order,
            $courierId,
            $collectionCompanyId,
            $manualShip,
            $manualColl,
            $shippingDate,
            $orderId,
            null,
        );

        $orderDetails->shipping_receivable_amount = $snapshot['shipping_amount'];
        $orderDetails->collection_receivable_amount = $snapshot['collection_amount'];
        $orderDetails->save();
    }

    private function postShippedReceivableSegments(
        Order $order,
        int $courierCompanyId,
        ?int $requestCollectionCompanyId,
        ?float $manualShippingAmount,
        ?float $manualCollectionAmount,
        string $shippingDate,
        int $orderId,
        ?float $basisNetTotal,
    ): array {
        /** @var ShippingReceivableSplitService $splitSvc */
        $splitSvc = app(ShippingReceivableSplitService::class);

        $suppressAutoCollection = $basisNetTotal !== null
            && abs((float) $basisNetTotal - (float) $order->net_total) > 0.02;

        $collectionIdForSplit = $requestCollectionCompanyId;
        if ($suppressAutoCollection && $manualShippingAmount === null && $manualCollectionAmount === null) {
            $collectionIdForSplit = null;
        }

        $split = $splitSvc->resolveForShip(
            $order,
            $courierCompanyId,
            $collectionIdForSplit,
            $manualShippingAmount,
            $manualCollectionAmount,
            $basisNetTotal,
        );

        $segments = $splitSvc->segmentsForProcedureCalls(
            $courierCompanyId,
            $split['collection_company_id'],
            $split['shipping_amount'],
            $split['collection_amount'],
        );

        foreach ($segments as $seg) {
            DB::statement('CALL shipping_company_procedure(?, ?, ?, ?, ?, ?, ?)', [
                $seg['company_id'],
                $orderId,
                $shippingDate,
                'تم شحن',
                $seg['amount'],
                auth()->user()->name,
                now(),
            ]);
        }

        return [
            'shipping_amount' => $split['shipping_amount'],
            'collection_amount' => $split['collection_amount'],
        ];
    }

    private function settleCollectionCompanyReceivableOnCollect(
        Order $order,
        Request $request,
        int $userId,
        string $paymentType,
        \App\Services\Orders\OrderManualCollectionGuard $collectionGuard,
    ): void {
        $order->loadMissing('order_details');
        $orderDetails = $order->order_details;
        if (! $orderDetails) {
            return;
        }

        $openAmount = round($collectionGuard->openCollectionReceivableAmount($order), 3);
        if ($openAmount <= 0.009) {
            return;
        }

        // طلبات مقسّمة (COD على المندوب + جزء على شركة تحصيل): التحصيل اليدوي يسوّي جزء
        // المندوب فقط، ويُترك جزء شركة التحصيل لسند «نقد وارد / شركة تحصيل».
        // نسوّي عبر تحصيل الطلب فقط عندما لا يوجد جزء COD على المندوب (طلب إلكتروني بالكامل مثل Paymob).
        if ($collectionGuard->shippingCodAmount($order) > 0.009) {
            return;
        }

        $collectionCompany = app(\App\Services\Shipping\CollectionCompanyForOrderResolver::class)
            ->resolve($order, persistLinkIfMissing: true);

        if (! $collectionCompany) {
            throw new \RuntimeException(
                'لا توجد شركة تحصيل مرتبطة بالطلب — راجع وسيلة الدفع (Shopify/Paymob) أو اربط شركة التحصيل من إدارة الشحن'
            );
        }

        // سطور legacy bridge على شركة الشحن المرتبطة بشركة التحصيل (إن وُجدت):
        // كل سطر يودِع النقد ويُسجِّل قيده عبر collectShippingCompanyDetailLine.
        $collectionShippingRows = shippingCompanyDetails::query()
            ->where('order_id', $order->id)
            ->whereIn('status', self::SHIPPING_DETAIL_OPEN_COLLECTION_STATUSES)
            ->where('is_done', 0)
            ->get()
            ->filter(function (shippingCompanyDetails $row) use ($collectionGuard, $orderDetails) {
                return $collectionGuard->isCollectionCompanyShippingRow(
                    (int) $row->shipping_company_id,
                    $orderDetails
                );
            });

        $collectedViaRows = 0.0;
        foreach ($collectionShippingRows as $elm) {
            $collectedViaRows += round((float) $elm->amount, 3);
            $this->collectShippingCompanyDetailLine(
                $elm,
                $order,
                $orderDetails,
                $request,
                $userId,
                $paymentType,
            );
        }

        // المتبقي بعد ما حُصِّل عبر السطور (يتجنّب الازدواج في إيداع النقد/القيد).
        $residual = round($openAmount - $collectedViaRows, 3);

        if ($residual > 0.009) {
            $creditAccountId = app(CollectionReceivableAccountResolver::class)
                ->receivableAccountIdForOrderDetails($orderDetails);

            $details = ' تحصيل من شركة تحصيل ' . ($collectionCompany->name ?? '');

            $this->applyOrderCollectionToPaymentSource(
                $order,
                $request,
                $residual,
                $userId,
                $details,
                $order->id,
                'الطلبات',
                $paymentType,
                $creditAccountId,
            );
        }

        $orderDetails->collection_receivable_amount = 0;
        $orderDetails->collection_status = OrderCollectionStatus::Collected->value;
        $orderDetails->settlement_status = OrderSettlementStatus::Settled->value;
        if (! $orderDetails->collection_date) {
            $orderDetails->collection_date = date('Y-m-d');
        }
        $orderDetails->save();
    }

    private function collectShippingCompanyDetailLine(
        shippingCompanyDetails $elm,
        Order $order,
        OrderDetails $orderDetails,
        Request $request,
        int $userId,
        string $paymentType,
    ): void {
        $collectAmount = round((float) $elm->amount, 3);
        $shippingCompanyId = (int) $elm->shipping_company_id;
        $shippingDate = $orderDetails->shipping_date;
        $userName = auth()->user()->name ?? 'system';

        if ($collectAmount > 0.0001) {
            DB::statement('CALL shipping_company_procedure(?, ?, ?, ?, ?, ?, ?)', [
                $shippingCompanyId,
                (int) $order->id,
                $shippingDate,
                'تم التحصيل',
                -$collectAmount,
                $userName,
                now(),
            ]);
        }

        $elm->is_done = 1;
        $elm->status = 'تم التحصيل';
        $elm->collect_date = date('Y-m-d');
        $elm->save();

        if ($collectAmount > 0.0001) {
            shippingCompanyDetails::query()
                ->where('shipping_company_id', $shippingCompanyId)
                ->where('order_id', $order->id)
                ->where('status', 'تم التحصيل')
                ->where('amount', '<', 0)
                ->where('is_done', 0)
                ->orderByDesc('id')
                ->limit(1)
                ->update(['is_done' => 1]);

            $shippingCompany = ShippingCompany::find($shippingCompanyId);
            $details = ' تحصيل من شركة شحن ' . ($shippingCompany->name ?? '');

            $this->applyOrderCollectionToPaymentSource(
                $order,
                $request,
                $collectAmount,
                $userId,
                $details,
                $order->id,
                'الطلبات',
                $paymentType,
                $this->resolveShippingReceivableAccountId($shippingCompany),
            );
        }
    }

    private function applyOrderCollectionToPaymentSource(
        Order $order,
        Request $request,
        float $amount,
        int $user_id,
        string $details,
        $ref,
        string $type,
        string $paymentType,
        ?int $creditReceivableAccountId = null,
    ): void {
        if ($amount <= 0) {
            return;
        }

        $ledger = app(\App\Services\Accounting\OrderPaymentSourceLedgerService::class);
        $date = now()->toDateString();

        if ($paymentType === 'safe' && $request->has('safe_id')) {
            $safe = \App\Models\Safe::find($request->safe_id);
            if ($safe) {
                $ledger->recordSafeMovement(
                    $safe,
                    $amount,
                    $order,
                    $details,
                    (string) $ref,
                    $type,
                    $user_id,
                    $date,
                    $creditReceivableAccountId,
                );
            }

            return;
        }

        if ($paymentType === 'service_account' && $request->has('service_account_id')) {
            $svc = \App\Models\ServiceAccount::find($request->service_account_id);
            if ($svc) {
                $ledger->recordServiceAccountMovement(
                    $svc,
                    $amount,
                    $order,
                    $details,
                    (string) $ref,
                    $type,
                    $user_id,
                    $date,
                    $creditReceivableAccountId,
                );
            }

            return;
        }

        $bankId = $request->bank_id ?? $request->bank ?? null;
        if ($paymentType === 'bank' && $bankId) {
            $bank = Bank::find($bankId);
            if ($bank) {
                $ledger->recordBankMovement(
                    $bank,
                    $amount,
                    $order,
                    $details,
                    (string) $ref,
                    $type,
                    $user_id,
                    $date,
                    $creditReceivableAccountId,
                );
            }
        }
    }

    private function resolveShippingReceivableAccountId(?ShippingCompany $shippingCompany): ?int
    {
        if (! $shippingCompany?->receivable_tree_account_id) {
            return null;
        }

        return app(\App\Services\Accounting\ReceivableTreeAccountGuard::class)
            ->sanitizeReceivableAccountId((int) $shippingCompany->receivable_tree_account_id);
    }

    /**
     * @deprecated Use OrderPaymentSourceLedgerService via applyOrderCollectionToPaymentSource
     */
    private function postCustomerCollectionAccounting(Order $order, float $amount, ?int $debitTreeAccountId, string $note): void
    {
        if (!$debitTreeAccountId || $amount <= 0) {
            return;
        }

        $accountLinkingService = app(\App\Services\Accounting\AccountLinkingService::class);
        $customerAccount = $accountLinkingService->resolveOrderCustomerAccount(
            $order->customer_type ?? 'فرد',
            $order->customer_name,
            $order->customer_phone_1,
            $order->company_id,
            $order->order_source_id ? (int) $order->order_source_id : null
        );

        if (!$customerAccount) {
            return;
        }

        try {
            app(LedgerJournalService::class)->postCustomerCollection(
                $customerAccount->id,
                $debitTreeAccountId,
                $amount,
                "تحصيل من العميل — طلب: {$order->id} — {$note}",
                $order->id,
                'COLLECT-' . $order->id . '-' . now()->format('YmdHis')
            );
        } catch (\Throwable $e) {
            Log::warning('postCustomerCollectionAccounting failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function postBankCollectionAccounting($order, $amount, $bankId, $note)
    {
        $bank = Bank::find($bankId);
        if (!$bank || !$bank->asset_id) {
            return;
        }
        $this->postCustomerCollectionAccounting($order, (float) $amount, (int) $bank->asset_id, $note);
    }

    /**
     * يحوّل مزود التحصيل (جديد أو legacy) إلى shipping_company_id للإجراء التشغيلي.
     */
    private function resolveCollectionCompanyIdForShip(Request $request): ?int
    {
        $type = $request->input('collection_provider_type');
        $providerId = $request->filled('collection_provider_id') ? (int) $request->collection_provider_id : null;

        if ($type && $providerId) {
            $legacy = CollectionProviderMorph::legacyCollectionCompanyId($type, $providerId);
            if ($legacy) {
                return $legacy;
            }
            if ($type === 'collection_company') {
                return null;
            }
            if (in_array($type, ['shipping_company', 'courier'], true)) {
                return $providerId;
            }
        }

        if ($request->filled('collection_company_id')) {
            return (int) $request->collection_company_id;
        }

        return null;
    }
}
