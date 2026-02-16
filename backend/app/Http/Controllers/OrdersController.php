<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Bank;
use App\Models\Note;
use App\Models\User;
use App\Models\Order;
use App\Models\Category;
use App\Models\tracking;
use App\Models\Notification;
use App\Models\OrderDetails;
use App\Models\OrderProduct;
use Illuminate\Http\Request;
use App\Filters\OrderFilters;
use App\Models\customerCompany;
use App\Models\OrderTempReview;
use App\Models\ShippingCompany;
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

class OrdersController extends Controller
{

    public function index()
    {
        $itemsPerPage = request('itemsPerPage') ?: 10;

        $orders = Order::with('order_details.shipping_line', 'order_details.shipping_company', 'shipping_method', 'order_products.category:id,category_name')
            ->orderBy('id', 'desc')
            ->paginate($itemsPerPage);

        return response()->json($orders, 200);
    }

    public function getTrackings()
    {
        $itemsPerPage = request('itemsPerPage') ?: 10;

        $tracking = tracking::with('order', 'user');

        if (request()->has('created_at')) {
            $tracking->whereDate('created_at', request('created_at'));
        }

        if (request()->has('user_id') && request('user_id') > 0) {
            $tracking->where('user_id', request('user_id'));
        }

        if (request()->has('action')) {
            $tracking->where('action', 'like', '%' . request('action') . '%');
        }

        $tracking = $tracking->orderBy('id', 'desc')
            ->paginate($itemsPerPage);

        return response()->json($tracking, 200);
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
        $order = Order::with([
            'shipping_method',
            'order_source',
            'order_details.shipping_line',
            'order_details.shipping_company',
            'bank',
            'maintenReason',
            'order_products.category',
            'order_products_archive.category',
            'order_shipment_number.user',
            'traking.user',
            'note.user',
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

        $order->tempReviewNotification = $order->notifications()
            ->where('type', 'مراجعة مؤقتة')
            ->where('review_status', '1')
            ->leftJoin('users', 'notifications.send_from', '=', 'users.id')
            ->where('users.department', '!=', 'Admin')
            ->select('notifications.*')
            ->get();

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

            $bank_id = null;
            if ($request->prepaid_amount != 0) {
                $bank_id = $request->bank;
            }

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
                'total_invoice' => $request->total_invoice,
                'prepaid_amount' => $request->prepaid_amount,
                'discount' => $request->discount,
                'net_total' => $request->net_total,
                'bank_id' => $bank_id,
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
                    $bankName  = Bank::find($request->bank);

                    $action = ' مبلغ تحت الحساب  ' . $request->prepaid_amount . ' في حساب ' . $bankName->name;
                    $this->insertTracking($order->id, $action, $user_id, now());

                    $company_id = $order->company_id;
                    $amount = (float)-$request->prepaid_amount;
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

            if ($request->has('order_notes') && $request->order_notes != '') {
                $note = $request->input('order_notes');
                $added_from = 'الاضافة';
            $this->insertNote($order->id, $user_id, $note, $added_from, now());
            }

            if ($request->has('prepaid_amount') && $request->prepaid_amount != '' && $request->prepaid_amount != 0) {
                $amount = (float)$request->prepaid_amount;
                $details = 'مبلغ تحت الحساب';
                $ref = $order->id;
                $type = 'الطلبات';

                $paymentType = $request->payment_type ?? 'bank'; // Default to bank for backward compatibility
                
                if ($paymentType === 'safe' && $request->has('safe_id')) {
                     $safeId = $request->safe_id;
                     $this->updateSafeBalance($safeId, $amount, $order->id, $user_id, $details, $ref, $type, now());
                     $this->handleDownPaymentAccounting($order, $amount, $safeId, $details, 'safe');
                } elseif ($paymentType === 'service_account' && $request->has('service_account_id')) {
                     $serviceAccountId = $request->service_account_id;
                     $this->updateServiceAccountBalance($serviceAccountId, $amount, $order->id, $user_id, $details, $ref, $type, now());
                     $this->handleDownPaymentAccounting($order, $amount, $serviceAccountId, $details, 'service_account');
                } else {
                     // Bank (Default)
                     $bankId = $request->bank; // $bank_id variable was set earlier but let's be explicit
                     if ($bankId) {
                        $this->updateBankBalance($bankId, $amount, $order->id, $user_id, $details, $ref, $type, now());
                        $this->handleDownPaymentAccounting($order, $amount, $bankId, $details, 'bank');
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

    public function edit($id, Request $request)
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
            $order = Order::where('id', $id)->first();

            if (
                !(in_array($order->order_type, ['جديد', 'طلب استبدال', 'طلب مرتجع']) &&
                    in_array($order->order_status, ['طلب جديد', 'طلب مؤكد', 'شحن جزئي'])) &&
                !($order->order_status === 'تم شحن' && (auth()->user()->department === 'Admin' || auth()->user()->department === 'Operation Management'))
            ) {
                return response()->json([
                    'message' => ' حالة الطلب الحاليه ' . $order->order_status . ' ونوع الطلب ' . $order->order_type . ' ولا يمكنك التعديل '
                ], 422);
            }


            $orderStatus = $order->order_status;
            $orderID = $order->id;

            if ($order->bank_id) {
                $bank_id = $order->bank_id;
            }
            if ($request->prepaid_amount != 0) {
                $bank_id = $request->bank_id;
            }
            if ($bank_id === 'null') {
                $bank_id = null;
            }

            $order = $order->update([
                'order_image' => $img_name,
                'shipping_cost' => $request->shipping_cost,
                'total_invoice' => $request->total_invoice,
                'prepaid_amount' => $request->prepaid_amount,
                'discount' => $request->discount,
                'net_total' => $request->net_total,
                'bank_id' => $bank_id,
                'vat' => $request->vat,
                'company_id' => $request->company_id
            ]);

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
                if ($orderStatus == 'تم شحن') {
                    Category::find($od->category_id)->increment('quantity', $od['shipped_quantity']);
                }
            }
            OrderProductArchive::insert($insertData);

            OrderDetails::where('order_id', $request->order_id)->increment('edits', 1);
            OrderProduct::where('order_id', $request->order_id)->delete();

            $order_details = $request->order_details;
            $order_details = json_decode($order_details, true);
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
                if ($orderStatus == 'تم شحن') {
                    $newOrderProduct->shipped_quantity = $od['quantity'];
                    $newOrderProduct->save();
                    Category::find($od['category_id'])->increment('quantity', -$od['quantity']);
                }
            }

            if ($request->has('prepaid_amount') && $request->prepaid_amount != '' && $request->prepaid_amount != 0) {
                $amount = (float)$request->prepaid_amount;
                $details = ' مبلغ تحت الحساب من تعديل الطلب رقم ' . $order->id;
                $ref = $order->id;
                $type = 'الطلبات';
                
                $paymentType = $request->payment_type ?? 'bank';

                if ($paymentType === 'safe' && $request->has('safe_id')) {
                    $this->updateSafeBalance($request->safe_id, $amount, $order->id, auth()->user()->id, $details, $ref, $type, now());
                } elseif ($paymentType === 'service_account' && $request->has('service_account_id')) {
                    $this->updateServiceAccountBalance($request->service_account_id, $amount, $order->id, auth()->user()->id, $details, $ref, $type, now());
                } else {
                    $bank = Bank::find($request->bank);
                    if ($bank) {
                         $this->updateBankBalance($bank->id, $amount, $order->id, auth()->user()->id, $details, $ref, $type, now());
                    }
                }
            }

            if ($orderStatus == 'تم شحن') {
                $shippingCompanyDetails = ShippingCompanyDetails::where('order_id', $orderID)->where('status', 'تم شحن')->where('is_done', 0)->first();
                $shippingCompanyDetails->old_amount = $shippingCompanyDetails->amount;
                $shippingCompanyDetails->amount = doubleval($request->net_total);
                $shippingCompanyDetails->update();
                ShippingCompany::find($shippingCompanyDetails->shipping_company_id)->increment('balance', -$shippingCompanyDetails->old_amount + $request->net_total);
                Order::where('id', $id)->update([
                    'collect_note' => $request->changed_collect_note
                ]);
            }

            $action = 'تعديل الطلب';
            $this->insertTracking($id, $action, auth()->user()->id, now());

            if ($request->has('order_notes') && $request->order_notes != '') {
                $note = $request->order_notes;
                $added_from = 'تعديل الطلب';
            $this->insertNote($order->id, auth()->user()->id, $note, $added_from, now());
            }

            $order_details = OrderDetails::where('order_id', $id)->first();
            $order_details->status_date = date('Y-m-d');
            $order_details->save();

            DB::commit();
            return response()->json(['message' => 'success'], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function refuseOrder(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $user_id = auth()->user()->id;
            $order = Order::find($id);
            if (!$order) {
                return response()->json(['message' => 'not found'], 404);
            }

            if (!($order->order_status == 'تم شحن')) {
                return response()->json(['message' => ' حالة الطلب الحاليه ' . $order->order_status], 422);
            }

            $order_details = OrderDetails::where('order_id', $id)->first();
            if ($order->order_status != 'تم شحن') {
                return response()->json(['message' => 'you can\'t do that'], 403);
            }

            if ($order->order_status == 'تم شحن') {
                $shipping_company = ShippingCompany::find($order->order_details->shipping_company_id);
                $orderdata = ShippingCompanyDetails::where('order_id', $id)->where('status', 'تم شحن')->where('is_done', 0)->latest()->first();
                $orderdata->is_done = 1;
                $orderdata->save();

                $shipping_company_id = (int)$shipping_company->id;
                $order_id = $id;
                $shipping_date = $order_details->shipping_date;
                $status = 'رفض استلام';
                $amount = (float)-$orderdata->amount;
                DB::statement('CALL shipping_company_procedure(?, ?, ?, ?, ?, ?, ?)', [
                    $shipping_company_id,
                    $order_id,
                    $shipping_date,
                    $status,
                    $amount,
                    auth()->user()->name,
                    now()
                ]);

                if ($request->bank != 'الخزينة') {
                    $amount = (float)$request->amount;
                    $details = ' تحصيل من شركة شحن ' . $shipping_company->name . ' لرفض استلام طلب ';
                    $ref = $order->id;
                    $type = 'الطلبات';
                    $this->updateBankBalance($request->bank, $amount, $order->id, $user_id, $details, $ref, $type, now());
                }

                if ($request->getorder == 'true') {
                    $products = OrderProduct::where('order_id', $request->id)->get();
                    foreach ($products as $product) {
                        if ($product['quantity'] > 0) {

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

                    $admin  = User::where('department', 'admin')->first();

                    if (auth()->id() !== $admin->id) {
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
            }


            $order->save();
            $order_details->reviewed = 0;
            $order_details->save();
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
                if ($order->customer_type == 'افراد') {
                    if ($request->has('moneyReturnedStatus')) {
                        $amount = (float)-$order->prepaid_amount;
                        $details = ' إرجاع مبلغ تحت الحساب الخاص بطلب رقم ' . $id;
                        $ref = $order->id;
                        $type = 'الطلبات';
                        $bank = null;
                        if ($request->moneyReturnedStatus === 'approved') {
                            $bank = $request->moneyReturnedBank;
                            $this->updateBankBalance($bank, $amount, $order->id, $user_id, $details, $ref, $type, now());
                        } else if ($request->moneyReturnedStatus === 'pending') {
                            $bank = $order->bank_id;
                            PendingBankBalance::create([
                                'amount' => $amount,
                                'details' => $details,
                                'ref' => $ref,
                                'type' => $type,
                                'bank_id' => $bank,
                                'user_id' => $user_id,
                            ]);
                        }
                    }
                }
                $order->order_status = 'ملغي';
                $order_details->canceled_date = date('Y-m-d');
                $order_details->status_date = date('Y-m-d');

                $action = 'طلب ملغي';
                $this->insertTracking($order->id, $action, $user_id, now());
                $admin  = User::where('department', 'admin')->first();

                if (auth()->id() !== $admin->id) {
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

                if (!($order->order_status == 'تم شحن')) {
                    return response()->json(['message' => ' حالة الطلب الحاليه ' . $order->order_status], 422);
                }

                $department = auth()->user()->department;
                if (!($department == 'Admin' || $department == 'Shipping Management' || $department == 'Operation Management' || $department == 'Operation Specialist' || $department == 'Logistics Specialist')) {
                    return response()->json(['message' => 'Forbidden'], 403);
                }

                $amountFromShipping = ShippingCompanyDetails::where('order_id', $id)->where('is_done', 0)->where('status', 'تم شحن')->sum('amount');
                if ($order->order_status == 'تم شحن') {
                    $order_products = OrderProduct::where('order_id', $id)->get();
                    foreach ($order_products as $product) {
                        // $product = OrderProduct::find($product['id']);
                        $product->shipped_quantity = 0;
                        $product->save();

                        $category_id = (int)$product->category_id;
                        $invoice_number = $id;
                        $type = 'رفض استلام طلب';
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

                        Category::find($category_id)->increment('sell_total_price', (float)- ($product['price'] * $product['quantity']));
                    }
                    $shippingDetails = ShippingCompanyDetails::where('order_id', $id)->where('status', 'تم شحن')->where('is_done', 0)->get();
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
                }
            } else if ($request->query('status') == 'postponed') {

                if (!(in_array($order->order_status, ['طلب جديد', 'طلب مؤكد', 'تم شحن']))) {
                    return response()->json(['message' => ' حالة الطلب الحاليه ' . $order->order_status], 422);
                }

                $department = auth()->user()->department;
                if (!($department == 'Admin' || $department == 'Shipping Management' || $department == 'Operation Management' || $department == 'Operation Specialist' || $department == 'Logistics Specialist')) {
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
                if (!($department == 'Admin' || $department == 'Data Entry' || $department == 'Shipping Management')) {
                    return response()->json(['message' => 'Forbidden'], 403);
                }

                $order->net_total = $order->net_total + $order->prepaid_amount;
                $order->prepaid_amount = 0;
                $order->bank_id = null;
                if ($request->has('renewAmount') && $request->has('renewBankId')) {
                    $order->net_total = $order->net_total - $request->renewAmount;

                    $order->prepaid_amount = $request->renewAmount;
                    $order->bank_id = $request->renewBankId;

                    $amount = (float)$request->renewAmount;
                    $details = 'مبلغ تحت الحساب من تجديد الطلب';
                    $ref = $order->id;
                    $type = 'الطلبات';
                    $bank_id = $request->renewBankId;
                    $this->updateBankBalance($bank_id, $amount, $order->id, $user_id, $details, $ref, $type, now());

                    $bankName  = Bank::find($bank_id);

                    $action = ' مبلغ تحت الحساب  ' . $request->renewAmount . ' في حساب ' . $bankName->name . ' من تجديد الطلب ';
                    $this->insertTracking($order->id, $action, $user_id, now());

                    if ($order->customer_type == 'شركة') {
                        $company_id = $order->company_id;
                        $amount = (float)-$request->renewAmount;
                        $ref = $order->id;
                        $details = 'مبلغ تحت الحساب من طلب رقم ' . $order->id . ' من تجديد الطلب ';
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

                $order->order_status = 'طلب جديد';
                $order_details->renew_date = date('Y-m-d');
                $order_details->status_date = date('Y-m-d');

                $action = 'تم تجديد الطلب';
                $this->insertTracking($order->id, $action, $user_id, now());

                if ($request->has('note') && $request->note != '') {
                    $note = $request->note;
                    $added_from = 'تجديد الطلب';
                    $this->insertNote($order->id, $user_id, $note, $added_from, now());
                }
            }

            $order->save();
            $order_details->reviewed = 0;
            $order_details->save();
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

        $request->validate([
            'date' => 'required',
            'company_id' => 'required|numeric|exists:shipping_companies,id',
            'productsToShip' => 'required',
        ]);

        DB::beginTransaction();
        try {
            $user_id = auth()->user()->id;
            if ($order->order_type == 'جديد') {
                $total = 0;
                $finshied = true;
                $productsToShip = json_decode($request->productsToShip, true);
                foreach ($productsToShip as $product) {
                    $order_product = OrderProduct::find($product['id']);
                    $order_product->shipped_quantity += (float)$product['quantity'];
                    $order_product->save();


                    $category_id = (float)$order_product->category_id;
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

                    if ($order_product->quantity != $order_product->shipped_quantity) {
                        $finshied = false;
                    }
                }


                $shipping_company = ShippingCompany::find($request->company_id);

                if ($finshied) {
                    if ($order->customer_type == 'افراد') {
                        $shipping_company_id = (float)$request->company_id;
                        $order_id = $id;
                        $shipping_date = $request->date;
                        $status = 'تم شحن';
                        $amount = (float)$order->net_total;
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

            OrderDetails::updateOrCreate(
                ['order_id' => $id],
                [
                    'status_date' => date('Y-m-d'),
                    'shipping_company_id' => $request->company_id,
                    'shippment_image' => $img_name,
                    'shipping_date' => $request->date,
                    'reviewed' => 0,
                ]
            );

            if ($request->shippment_number) {
                OrderShippingNumber::create([
                    'order_id' => $id,
                    'shipment_number' => $request->shippment_number,
                    'user_id' => auth()->user()->id
                ]);
            }

            if ($order->customer_type != 'شركة' && $order->order_type != 'جديد') {

                $action = 'تم شحن الطلب';
                $this->insertTracking($order->id, $action, $user_id, now());

                $amount = $order->net_total;

                $existFirstPay = ShippingCompanyDetails::where('order_id', $id)->get();

                if ($order->order_type == 'طلب صيانة' && $existFirstPay->count() > 0) {
                    $collect = ShippingCompanyDetails::where('order_id', $id)->where('status', 'تم التحصيل')->get();

                    if ($collect->count() > 0) {
                        $amount = $order->net_total;
                    } else {
                        $amount = $order->net_total - $existFirstPay[0]->amount;
                    }
                }

                $shipping_company_id = (float)$request->company_id;
                $order_id = $id;
                $shipping_date = $request->date;
                $status = 'تم شحن';
                $amount = (float)$amount;
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


            if ($order->order_type != 'طلب صيانة' && ($order->customer_type != 'شركة' && $order->order_type != 'جديد')) {
                $order_products = OrderProduct::where('order_id', $id)->get();
                foreach ($order_products as $op) {
                    if ($op->quantity > 0) {
                        $cat =  Category::find($op->category_id);
                        $cat->quantity = $cat->quantity - $op->quantity;
                        $cat->save();
                        $op->shipped_quantity = $op->quantity;
                        $op->save();
                    }
                }
            }

            DB::commit();
            return response()->json(['message' => 'success'], 200);
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
        $user_id = auth()->user()->id;
        $note = $request->value;
        $added_from = 'تفاصيل الطلب';
        $this->insertNote($id, $user_id, $note, $added_from, now());
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
        $order = Order::with('order_details')->find($id);

        if (!$order) {
            return response()->json(['message' => 'not found'], 404);
        }

        if (!($order->order_status == 'تم شحن' && ($order->order_type != 'طلب صيانة' || $order->order_details->maintenance_date))) {
            return response()->json(['message' => ' حالة الطلب الحاليه ' . $order->order_status], 422);
        }

        DB::beginTransaction();
        try {
            $user_id = auth()->user()->id;
            $order_details = $order->order_details;
            $order_details->collection_date = date('Y-m-d');
            $order_details->status_date = date('Y-m-d');
            $order_details->reviewed = 0;
            $order_details->save();
            $order->order_status = 'تم التحصيل';
            $order->save();

            $action = 'تم تحصيل الطلب';
            $this->insertTracking($order->id, $action, $user_id, now());

            if ($request->has('note') && $request->note != '') {
                $note = $request->note;
                $added_from = 'تحصيل الطلب';
            $this->insertNote($order->id, $user_id, $note, $added_from, now());
            }

            $shippingDetails = ShippingCompanyDetails::where('order_id', $id)->where('status', 'تم شحن')->where('is_done', 0)->get();
            if ($order->customer_type == 'شركة' && $order->order_status == 'تم التحصيل') {
                $collectFromCompanies = 0;
                foreach ($shippingDetails as $elm) {
                    $collectFromCompanies += $elm->amount;
                    // $orderdata = ShippingCompanyDetails::where('id',$elm->id)->first();
                    $elm->is_done = 1;
                    $elm->save();

                    $shipping_company_id = (float)$elm->shipping_company_id;
                    $order_id = $id;
                    $shipping_date = $order_details->shipping_date;
                    $status = 'تم التحصيل';
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

                    $shipping_company = ShippingCompany::find($elm->shipping_company_id);

                    $amount = (float)$elm->amount;
                    $details = ' تحصيل من شركة شحن ' . $shipping_company->name;
                    $ref = $order->id;
                    $type = 'الطلبات';

                    $paymentType = $request->payment_type ?? 'bank';
                    if ($paymentType === 'safe' && $request->has('safe_id')) {
                        $this->updateSafeBalance($request->safe_id, $amount, $order->id, $user_id, $details, $ref, $type, now());
                    } elseif ($paymentType === 'service_account' && $request->has('service_account_id')) {
                        $this->updateServiceAccountBalance($request->service_account_id, $amount, $order->id, $user_id, $details, $ref, $type, now());
                    } else {
                        $this->updateBankBalance($request->bank_id, $amount, $order->id, $user_id, $details, $ref, $type, now());
                    }
                }

                $company = CustomerCompany::find($order->company_id);
                if ($company->id) {
                    $amount = (float)($order->net_total - $collectFromCompanies);
                    $details = ' تحصيل من عميل شركة ' . $company->name;
                    $ref = $order->id;
                    $type = 'الطلبات';
                    
                    if ($paymentType === 'safe' && $request->has('safe_id')) {
                        $this->updateSafeBalance($request->safe_id, $amount, $order->id, $user_id, $details, $ref, $type, now());
                    } elseif ($paymentType === 'service_account' && $request->has('service_account_id')) {
                        $this->updateServiceAccountBalance($request->service_account_id, $amount, $order->id, $user_id, $details, $ref, $type, now());
                    } else {
                        $this->updateBankBalance($request->bank_id, $amount, $order->id, $user_id, $details, $ref, $type, now());
                    }




                    $company_id = $order->company_id;
                    $amount = number_format((float)- ($order->net_total - $collectFromCompanies + $order->shipping_cost + $order->discount), 3, '.', '');
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
                        now()
                    ]);
                }
            } else {

                $collectFromShippingCompany = 0;
                foreach ($shippingDetails as $elm) {
                    $collectFromShippingCompany += $elm->amount;
                }


                if ($order->order_type == 'طلب صيانة') {
                    $orders = ShippingCompanyDetails::where('order_id', $id)->where('status', 'تم شحن')->where('is_done', 0)->get();
                    foreach ($orders as $elm) {
                        $elm->is_done = true;
                        $elm->save();

                        $shipping_company_id = (float)$elm->shipping_company_id;
                        $order_id = $request->id;
                        $shipping_date = $order_details->shipping_date;
                        $status = 'تم التحصيل';
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

                        $shipping_company = ShippingCompany::find($elm->shipping_company_id);

                        $amount = (float)($elm->amount);
                        $details = ' تحصيل من شركة شحن ' . $shipping_company->name;
                        $ref = $request->id;
                        $type = 'الطلبات';
                        $this->updateBankBalance($request->bank_id, $amount, $order->id, $user_id, $details, $ref, $type, now());
                    }
                } else {
                    $shipping_company = ShippingCompany::find($order->order_details->shipping_company_id);
                    $order = ShippingCompanyDetails::where('order_id', $id)->latest()->first();
                    $order->is_done = true;
                    $order->save();

                    $shipping_company_id = (float)$order->shipping_company_id;
                    $order_id = $request->id;
                    $shipping_date = $order_details->shipping_date;
                    $status = 'تم التحصيل';
                    $amount = (float)-$order->amount;
                    DB::statement('CALL shipping_company_procedure(?, ?, ?, ?, ?, ?, ?)', [
                        $shipping_company_id,
                        $order_id,
                        $shipping_date,
                        $status,
                        $amount,
                        auth()->user()->name,
                        now()
                    ]);

                    $shippingCompanyDetails = ShippingCompanyDetails::where('order_id', $id)->where('status', 'تم التحصيل')->first();
                    if ($order->old_amount) {
                        $shippingCompanyDetails->old_amount = (float)-$order->old_amount;
                        $shippingCompanyDetails->update();
                    }

                    if ($request->has('reference_number') || $request->hasFile('reference_image')) {
                        $shippingCompanyDetails->old_amount = (float)-$order->amount;
                        $shippingCompanyDetails->amount = 0;
                        $shippingCompanyDetails->update();

                        $img_name = '';
                        if ($request->hasFile('reference_image')) {
                            $img = $request->file('reference_image');
                            $img_name = time() . '.' . $img->extension();
                            $img->move(public_path('images'), $img_name);
                        }

                        Order::where('id', $id)->update([
                            'reference_image' => $img_name,
                            'reference_number' => $request->reference_number
                        ]);
                    } else {
                        $amount = (float)($order->amount);
                        $details = ' تحصيل من شركة شحن ' . $shipping_company->name;
                        $ref = $request->id;
                        $type = 'الطلبات';
                        $this->updateBankBalance($request->bank_id, $amount, $order->id, $user_id, $details, $ref, $type, now());
                    }
                }

                if ($request->receivedOrder) {
                    $products = OrderProduct::where('order_id', $request->id)->get();
                    foreach ($products as $product) {
                        if ($product->quantity < 0) {
                            $cat = Category::where('id', $product->category_id)->first();
                            $cat->quantity = $cat->quantity - $product->quantity;
                            $cat->save();
                        }
                    }
                } else {
                    if ($request->has('reason') && $request->reason != '') {
                        $admin  = User::where('department', 'admin')->first();
                        if (auth()->id() !== $admin->id) {
                            Notification::create([
                                'send_from' =>  auth()->id(),
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
                            'is_problem' => true
                        ]);
                    }
                }
            }


            if ($order->order_type != 'طلب صيانة') {
                $products = OrderProduct::where('order_id', $order->id)->get();
                foreach ($products as $product) {
                    if ($product->quantity < 0) {
                        $cat = Category::where('id', $product->category_id)->first();
                        $cat->quantity = $cat->quantity - $product->quantity;
                        $cat->save();
                    }
                }
            }

            DB::commit();
            return response()->json(['message' => 'success'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 500);
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
                DB::statement('CALL update_customer_company_balance(?, ?, ?, ?, ?, ?, ?, ?)', [
                    $company_id,
                    $amount,
                    $request->bank_id,
                    $ref,
                    $details,
                    $type,
                    $user_id,
                    now()
                ]);

                $bank = Bank::find($request->bank_id);

                $amount = (float)$request->amount;
                $details = ' تحصيل جزئي من عميل شركة    ' . $company->name;
                $ref = $order->id;
                $type = 'الطلبات';
                $this->updateBankBalance($request->bank_id, $amount, $order->id, $user_id, $details, $ref, $type, now());

                $action = ' تحصيل جزئي مبلغ ' . $request->amount . ' في حساب ' . $bank->name;
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

        $userDepartment = auth()->user()->department;
        $userId = auth()->user()->id;

        $roleOrderStatuses = [
            'Operation Management' => ["طلب مؤكد","شحن جزئي","تم شحن","تم الاستلام","مؤجل","تم الصيانة", "رفض استلام"],
            'Operation Specialist' => ["طلب مؤكد","شحن جزئي","تم شحن","تم الاستلام","مؤجل","تم الصيانة", "رفض استلام"],
            'Logistics Specialist' => ["طلب مؤكد","شحن جزئي","تم شحن","تم الاستلام","مؤجل","تم الصيانة", "رفض استلام"],
            'Shipping Management' => ["طلب جديد", "طلب مؤكد","شحن جزئي","تم شحن","تم الاستلام", "تم التحصيل","مؤجل","تم الصيانة", "رفض استلام","ملغي"],
            'Review Management' => ["تم شحن","تم التحصيل","مؤجل","ملغي", "رفض استلام"],
        ];

        $privateOrder = $userDepartment == 'Admin' ? 1 : null;
        $orderStatusArray = $roleOrderStatuses[$userDepartment] ?? [];

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

        if (!empty($orderStatusArray)) {
            $order->where(function ($query) use ($orderStatusArray, $userId) {
                $query->whereIn('order_status', $orderStatusArray)
                        ->orWhereHas('notifications', function ($notificationQuery) use ($userId) {
                    $notificationQuery->where('send_to', $userId);
                });
            });
        }

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

        $orders = $order->with([
            'order_details.shipping_line',
            'order_details.shipping_company',
            'shipping_method',
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
            DB::raw('MAX(governorate) as governorate'),
            DB::raw('MAX(city) as city'),
            DB::raw('COUNT(orders.id) as orders_count'),
            DB::raw('SUM(net_total) as total_debit'),
            DB::raw('SUM(prepaid_amount) as total_credit')
        )
        ->groupBy('customer_phone_1')
        ->orderByDesc(DB::raw('MAX(orders.id)'));

    $customers = $query->simplePaginate($itemsPerPage);

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
    private function updateBankBalance($bank_id, $amount, $order_id, $user_id, $details, $ref, $type, $created_at)
    {
        $bank = DB::table('banks')->where('id', $bank_id)->first();
        if ($bank) {
            $current_balance = $bank->balance;
            $new_balance = $current_balance + $amount;

            DB::table('banks')->where('id', $bank_id)->update(['balance' => $new_balance]);

            DB::table('bank_details')->insert([
                'bank_id' => $bank_id,
                'details' => $details,
                'ref' => $ref,
                'type' => $type,
                'amount' => $amount,
                'balance_before' => $current_balance,
                'balance_after' => $new_balance,
                'date' => date('Y-m-d'),
                'created_at' => $created_at,
                'user_id' => $user_id
            ]);
        }
    }

        private function handleDownPaymentAccounting($order, $amount, $sourceId, $note, $sourceType = 'bank')
    {
        // 1. Bank/Safe Tree Account (Debit)
        $debitTreeId = null;
        $sourceName = '';
        
        if ($sourceType === 'bank') {
            $bank = \App\Models\Bank::find($sourceId);
            if ($bank && $bank->asset_id) {
                $debitTreeId = $bank->asset_id;
                $sourceName = $bank->name;
            }
        } elseif ($sourceType === 'safe') {
            $safe = \App\Models\Safe::find($sourceId);
            if ($safe && $safe->account_id) {
                $debitTreeId = $safe->account_id;
                $sourceName = $safe->name;
            }
        } elseif ($sourceType === 'service_account') {
            $account = \App\Models\ServiceAccount::find($sourceId);
            if ($account && $account->account_id) {
                $debitTreeId = $account->account_id;
                $sourceName = $account->name;
            }
        }
        
        if (!$debitTreeId) return;

        // 2. Customer Tree Account (Credit)
        $customerTreeId = null;
        if ($order->customer_type == 'شركة' && $order->company_id) {
            $company = \App\Models\customerCompany::find($order->company_id);
            if ($company) {
                if (!$company->tree_account_id) {
                    // Create Tree Account for Company
                    $parentAccountId = \App\Models\Setting::where('key', 'customer_corporate_parent_account_id')->value('value');
                    
                    $parentAccount = null;
                    if ($parentAccountId) {
                        $parentAccount = \App\Models\TreeAccount::find($parentAccountId);
                    }
                    
                    if (!$parentAccountId || !$parentAccount) {
                        $parentAccount = \App\Models\TreeAccount::where('name', 'like', '%العملاء%')->first();
                        if (!$parentAccount) {
                             $parentAccount = \App\Models\TreeAccount::firstOrCreate(
                                ['name' => 'العملاء'],
                                ['type' => 'asset', 'balance' => 0, 'code' => '1100'] 
                            );
                        }
                    }

                    $checkCode = \App\Models\TreeAccount::where('code', $parentAccount->code . $company->id)->first();
                     $newAccount = \App\Models\TreeAccount::create([
                        'name' => $company->name,
                        'parent_id' => $parentAccount->id,
                        'code' => $checkCode ? $parentAccount->code . $company->id . rand(10,99) : $parentAccount->code . $company->id,
                        'type' => 'asset',
                        'balance' => 0
                    ]);
                    $company->tree_account_id = $newAccount->id;
                    $company->save();
                }
                $customerTreeId = $company->tree_account_id;
            }
        } else {
             // Individual Customer
             $phone = $order->customer_phone_1;
             $accName = $order->customer_name . ' - ' . $phone;
             
             // Check if account already exists
             $indAccount = \App\Models\TreeAccount::where('name', $accName)->first();
             
             if ($indAccount) {
                 $customerTreeId = $indAccount->id;
             } else {
                 // Create new account
                 $parentAccountId = \App\Models\Setting::where('key', 'customer_individual_parent_account_id')->value('value');
                 
                 $parentAccount = null;
                 if ($parentAccountId) {
                     $parentAccount = \App\Models\TreeAccount::find($parentAccountId);
                 }
                 
                 if (!$parentAccountId || !$parentAccount) {
                     $parentAccount = \App\Models\TreeAccount::where('name', 'like', '%عملاء افراد%')->first();
                     if (!$parentAccount) {
                         $parentMain = \App\Models\TreeAccount::where('name', 'like', '%العملاء%')->first();
                         if (!$parentMain) {
                              $parentMain = \App\Models\TreeAccount::create(['name' => 'العملاء', 'type' => 'asset', 'code' => '1100', 'balance'=>0]);
                         }
                         $parentAccount = \App\Models\TreeAccount::create([
                             'name' => 'عملاء افراد',
                             'parent_id' => $parentMain->id,
                             'code' => $parentMain->code . '999',
                             'type' => 'asset',
                             'balance' => 0
                         ]);
                     }
                 }

                 $indAccount = \App\Models\TreeAccount::create([
                     'name' => $accName,
                     'parent_id' => $parentAccount->id,
                     'code' => $parentAccount->code . substr($phone, -4) . rand(10,99),
                     'type' => 'asset',
                     'balance' => 0
                 ]);
                 $customerTreeId = $indAccount->id;
             }
        }

        if ($customerTreeId) {
            // Generate Daily Entry
             $lastEntry = \App\Models\DailyEntry::orderByDesc('entry_number')->first();
             $entryNumber = $lastEntry ? (int)$lastEntry->entry_number + 1 : 1;
 
             $dailyEntry = \App\Models\DailyEntry::create([
                 'date' => now(),
                 'entry_number' => str_pad($entryNumber, 6, '0', STR_PAD_LEFT),
                 'description' => "دفعة مقدمة - طلب: " . $order->id . " - " . $note,
                 'user_id' => auth()->id(),
             ]);

             // Debit Item (Bank/Safe)
             \App\Models\DailyEntryItem::create([
                'daily_entry_id' => $dailyEntry->id,
                'account_id' => $debitTreeId,
                'debit' => $amount,
                'credit' => 0,
                'notes' => "محصل في " . ($sourceType == 'safe' ? 'الخزينة' : ($sourceType == 'service_account' ? 'حساب خدمي' : 'البنك')),
             ]);

             // Credit Item (Customer)
             \App\Models\DailyEntryItem::create([
                'daily_entry_id' => $dailyEntry->id,
                'account_id' => $customerTreeId,
                'debit' => 0,
                'credit' => $amount,
                'notes' => "تحصيل من العميل",
             ]);

             // Debit AccountEntry
            \App\Models\AccountEntry::create([
                'tree_account_id' => $debitTreeId,
                'debit' => $amount,
                'credit' => 0,
                'description' => "دفعة مقدمة - طلب: " . $order->id . " - " . $note,
                'daily_entry_id' => $dailyEntry->id, // Link to Daily Entry
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $bankAcc = \App\Models\TreeAccount::find($debitTreeId);
            $bankAcc->increment('debit_balance', $amount);
            $bankAcc->increment('balance', $amount); // Asset increases

            // Credit AccountEntry
            \App\Models\AccountEntry::create([
                'tree_account_id' => $customerTreeId,
                'debit' => 0,
                'credit' => $amount,
                'description' => "دفعة مقدمة - طلب: " . $order->id . " - " . $note,
                 'daily_entry_id' => $dailyEntry->id, // Link to Daily Entry
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $custAcc = \App\Models\TreeAccount::find($customerTreeId);
            $custAcc->increment('credit_balance', $amount);
            $custAcc->decrement('balance', $amount); // Asset decreases
        }
    }


private function updateSafeBalance($safe_id, $amount, $order_id, $user_id, $details, $ref, $type, $created_at)
{
    $safe = \App\Models\Safe::find($safe_id);
    if ($safe) {
        $current_balance = $safe->balance;
        $new_balance = $current_balance + $amount;

        $safe->update(['balance' => $new_balance]);
        
        // Transaction
        \App\Models\SafeTransaction::create([
             'from_safe_id' => $safe_id, // Or null? Typically for income, safe is target. But transaction model structure is Transfer-based.
             // Looking at SafeTransaction: from_safe_id, to_safe_id. 
             // If it's a deposit, maybe there is no "from"? Or we just use it as a log?
             // Since SafeTransaction seems to be for TRANSFERS mostly, maybe we shouldn't use it for simple income/expense? 
             // Wait, the user wants "Show in Journal". 
             // SafeController only uses SafeTransaction for transfers. 
             // Let's check if there is a 'safe_details' table? No.
             // If we want to track movement, maybe we should just rely on the AccountEntry (Journal).
             // However, Bank has `bank_details`. Safe doesn't seems to have `safe_details`.
             // I will stick to updating the Safe Balance + Journal Entry (AccountEntry).
             // If a log is needed, maybe create a Note or just rely on AccountEntry.
             // Re-reading user request: "Show this order in the journal". Journal = AccountEntry. 
             // So updating Safe Balance + AccountEntry is sufficient.
             // But wait, the standard way in this system seems to be keeping a history table (bank_details).
             // Since safe_details doesn't exist, I will just update balance.
        ]);
        
        // Actually, let's look at VoucherController. It just updates Safe/Bank balance? 
        // VoucherController updates TreeAccount (Journal) and `updateOperationalBalance`.
        // `updateOperationalBalance` updates `customer_company_details` or `supplier_balance`.
        // It DOES NOT seem to insert into a `safe_details` table.
        // So for Safes, the Journal IS the log. 
    }
}
    private function updateServiceAccountBalance($service_account_id, $amount, $order_id, $user_id, $details, $ref, $type, $created_at)
    {
        $account = \App\Models\ServiceAccount::find($service_account_id);
        if ($account) {
            $current_balance = $account->balance;
            $new_balance = $current_balance + $amount;

            $account->update(['balance' => $new_balance]);
        }
    }
}
