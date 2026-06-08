<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\ShippingCompany;
use App\Models\TreeAccount;
use App\Models\shippingCompanyDetails;
use App\Services\Accounting\AccountLinkingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;


class ShippingCompanyController extends Controller
{
    public function __construct(public AccountLinkingService $accountLinkingService)
    {
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $shippingCompanies = ShippingCompany::with('receivableTreeAccount:id,code,name')->get();

        // عمود shipping_companies.orders_count في الجدول قيمة قديمة (افتراضي 0) ولا يُحدَّث مع الشحن.
        // العدد الحقيقي «تحت التحصيل» = صفوف shipping_company_details بحالة تم شحن ولم تُغلق بعد.
        $pendingByCompany = DB::table('shipping_company_details')
            ->select('shipping_company_id', DB::raw('COUNT(DISTINCT order_id) as cnt'))
            ->where('status', 'تم شحن')
            ->where('is_done', 0)
            ->groupBy('shipping_company_id')
            ->pluck('cnt', 'shipping_company_id');

        $payload = $shippingCompanies->map(function (ShippingCompany $sc) use ($pendingByCompany) {
            $row = $sc->toArray();
            $row['orders_count'] = (int) ($pendingByCompany[$sc->id] ?? 0);

            return $row;
        });

        return response()->json($payload->values(), 200);
    }

    public function shippingcompanySelect()
    {
        $data = ShippingCompany::select('id', 'name', 'type')->get();
        return response()->json($data, 200);
    }

    /**
     * عدد شركات الشحن والمناديب غير المربوطين بحسابات الشجرة.
     */
    public function unlinkedSummary()
    {
        $unlinkedCount = ShippingCompany::query()->whereNull('receivable_tree_account_id')->count();
        $parent = $this->accountLinkingService->getShippingReceivableParent();

        return response()->json([
            'unlinked_count' => $unlinkedCount,
            'parent_account' => $parent ? [
                'id' => $parent->id,
                'name' => $parent->name,
                'code' => (string) $parent->code,
            ] : null,
        ]);
    }

    /**
     * ربط جميع شركات الشحن والمناديب غير المربوطين دفعة واحدة.
     */
    public function linkUnlinked()
    {
        $result = $this->accountLinkingService->linkAllUnlinkedShippingCompanies();

        if (!$result['parent']) {
            return response()->json([
                'message' => $result['message'],
            ], 422);
        }

        $status = $result['failed'] > 0 ? 207 : 200;

        return response()->json($result, $status);
    }

    /**
     * ربط شركة شحن/مندوب واحد بحساب في شجرة الحسابات.
     */
    public function linkAccount($id)
    {
        $company = ShippingCompany::find($id);
        if (!$company) {
            return response()->json(['message' => 'الشركة غير موجودة'], 404);
        }

        if ($company->receivable_tree_account_id) {
            $account = TreeAccount::find($company->receivable_tree_account_id);

            return response()->json([
                'message' => 'الشركة مربوطة بالفعل بحساب في الشجرة.',
                'receivable_tree_account_id' => $company->receivable_tree_account_id,
                'receivable_tree_account' => $account ? [
                    'id' => $account->id,
                    'name' => $account->name,
                    'code' => (string) $account->code,
                ] : null,
            ]);
        }

        $account = $this->accountLinkingService->ensureShippingCompanyAccount($company);
        if (!$account) {
            return response()->json([
                'message' => 'تعذر إنشاء حساب للشركة. راجع حساب «شركات الشحن والمناديب» في شجرة الحسابات.',
            ], 422);
        }

        return response()->json([
            'message' => 'success',
            'receivable_tree_account_id' => $account->id,
            'receivable_tree_account' => [
                'id' => $account->id,
                'name' => $account->name,
                'code' => (string) $account->code,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'type' => 'required|in:مندوب,شركة',
            'receivable_tree_account_id' => 'nullable|integer|exists:tree_accounts,id',
        ]);

        $company = ShippingCompany::create([
            'name' => $request->name,
            'type' => $request->type,
            'receivable_tree_account_id' => $request->filled('receivable_tree_account_id')
                ? (int) $request->receivable_tree_account_id
                : null,
        ]);

        if (!$company->receivable_tree_account_id) {
            $account = $this->accountLinkingService->ensureShippingCompanyAccount($company);
            if (!$account) {
                return response()->json([
                    'message' => 'تم إنشاء الشركة لكن تعذر إنشاء حسابها في شجرة الحسابات. راجع حساب «شركات الشحن والمناديب» أو إعدادات الربط المحاسبي.',
                ], 422);
            }
        } else {
            $account = TreeAccount::find($company->receivable_tree_account_id);
        }

        return response()->json([
            'message' => 'created',
            'receivable_tree_account_id' => $company->receivable_tree_account_id,
            'receivable_tree_account' => $account ? [
                'id' => $account->id,
                'name' => $account->name,
                'code' => (string) $account->code,
            ] : null,
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $itemsPerPage = request('itemsPerPage') ?: 10;

        $orderDetails = OrderDetails::where('shipping_company_id', $id)
            ->with(['order:id,customer_name,customer_phone_1,order_date,order_status,net_total','shipping_company:id,name'])
            ->orderBy('id', 'desc')
            ->paginate($itemsPerPage);

            $totalNet = Order::whereHas('order_details', function ($query) use ($id) {
                $query->where('shipping_company_id', $id);
            })->sum('net_total');


            // $orderDetails['totalNet'] = $totalNet;

            $response = [
                'orderDetails' => $orderDetails,
                'totalNet' => $totalNet,
            ];

        return response()->json($response, 200);
    }

    public function search(Request $request)
    {
        $id = $request->id;
        $itemsPerPage = $request->itemsPerPage ?? 15;

        $search = shippingCompanyDetails::query();

        if ($request->has('id')) {
            $search->where('shipping_company_id', $id);
        }

        if ($request->has('shippingDate')) {
            $search->where(function ($query) use ($request) {
                $query->where('shipping_date', $request->shippingDate);
            });
        }

        if ($request->has('order_status')) {
            // Do not filter by is_done: rows are marked is_done=1 after collection/refusal/etc.
            // while status may still be "تم شحن"; excluding them emptied كشف حساب شركة الشحن.
            $search->where('status', $request->order_status);
        }


        if ($request->has('collectDate')) {
            $search->where(function ($query) use ($request) {
                $query->where('collect_date', $request->collectDate);
            });
        }

        if ($request->has('reviewed')) {
            $search->whereHas('order.order_details', function ($query) use ($request) {
                $query->where('reviewed', $request->reviewed);
            });
        }

        $search->with([
            'order' => function ($query) {
                $query->with([
                    'order_details',
                    'traking' => function ($q) {
                        $q->orderByDesc('id')
                            ->limit(8)
                            ->select(['id', 'order_id', 'action', 'date', 'user_id', 'created_at'])
                            ->with('user:id,name');
                    },
                ])->withCount([
                    'notifications as review_notifications_count' => function ($query) {
                        $query->where('type', 'مراجعة')
                            ->where('send_from', auth()->id());
                    }
                ]);
            },
        ]);
        $search->orderBy('id', 'desc');

        $allData= $search->get();

        $totalNet = $allData->map(function ($item) {
            return $item->amount;
        })->sum();

        $search = $search->paginate($itemsPerPage);

        $pageOrderIds = $search->getCollection()->pluck('order_id')->unique()->filter()->values();
        foreach ($pageOrderIds as $oid) {
            Order::reconcileCollectionStatusIfAllShippingLinesClosed((int) $oid);
        }
        $search->getCollection()->each(function ($row) {
            if ($row->relationLoaded('order') && $row->order) {
                $row->order->refresh();
            }
        });

        $name = ShippingCompany::find($id);

        $response = [
            'orderDetails' => $search,
            'totalNet' => $totalNet,
            'name' => $name,
        ];



        return response()->json($response, 200);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $companyToUpdate = ShippingCompany::find($id);
        if(!$companyToUpdate){
            return response()->json("not found", 404);
        }

        $request->validate([
            'name' => 'required',
            'type' => 'required|in:مندوب,شركة',
            'receivable_tree_account_id' => 'nullable|integer|exists:tree_accounts,id',
        ]);

        $companyToUpdate->update([
            'name' => $request->name,
            'type' => $request->type,
            'receivable_tree_account_id' => $request->filled('receivable_tree_account_id')
                ? (int) $request->receivable_tree_account_id
                : null,
        ]);

        if (!$companyToUpdate->receivable_tree_account_id) {
            $account = $this->accountLinkingService->ensureShippingCompanyAccount($companyToUpdate);
            if (!$account) {
                return response()->json([
                    'message' => 'تم حفظ بيانات الشركة لكن تعذر ربطها بحساب في شجرة الحسابات.',
                ], 422);
            }
        } else {
            $account = TreeAccount::find($companyToUpdate->receivable_tree_account_id);
            $this->accountLinkingService->syncShippingCompanyTreeAccountName($companyToUpdate);
        }

        return response()->json([
            'message' => 'updated',
            'receivable_tree_account_id' => $companyToUpdate->receivable_tree_account_id,
            'receivable_tree_account' => $account ? [
                'id' => $account->id,
                'name' => $account->name,
                'code' => (string) $account->code,
            ] : null,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $companyToDelete = ShippingCompany::find($id);
        if(!$companyToDelete){
            return response()->json("not found", 404);
        }
        $companyToDelete->delete();

        return response()->json("deleted", 200);
    }

    /**
     * تقرير أداء شركات الشحن حسب فترة (من سجلات shipping_company_details).
     */
    public function shippingCompaniesReport(Request $request)
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'q' => 'nullable|string|max:255',
        ]);

        $from = $validated['date_from'];
        $to = $validated['date_to'];
        $q = isset($validated['q']) ? trim($validated['q']) : '';

        $bindings = [$from, $to, $from, $to, $from, $to];
        $where = '';
        if ($q !== '') {
            $where = 'WHERE sc.name LIKE ?';
            $bindings[] = '%'.$q.'%';
        }

        $sql = "
            SELECT sc.id,
                sc.name,
                sc.type,
                COUNT(DISTINCT CASE
                    WHEN scd.status = 'تم شحن'
                    AND DATE(COALESCE(NULLIF(scd.shipping_date, '0000-00-00'), scd.created_at)) BETWEEN ? AND ?
                    THEN scd.order_id END) AS shipped_count,
                COUNT(DISTINCT CASE
                    WHEN scd.status = 'تم التحصيل'
                    AND DATE(COALESCE(NULLIF(scd.collect_date, '0000-00-00'), scd.created_at)) BETWEEN ? AND ?
                    THEN scd.order_id END) AS collected_count,
                COUNT(DISTINCT CASE
                    WHEN scd.status = 'رفض استلام'
                    AND DATE(COALESCE(NULLIF(scd.collect_date, '0000-00-00'), scd.created_at)) BETWEEN ? AND ?
                    THEN scd.order_id END) AS refused_count
            FROM shipping_companies sc
            LEFT JOIN shipping_company_details scd ON scd.shipping_company_id = sc.id
            {$where}
            GROUP BY sc.id, sc.name, sc.type
            ORDER BY sc.name
        ";

        $rows = DB::select($sql, $bindings);

        $reasonWhere = '';
        $reasonParams = [$from, $to];
        if ($q !== '') {
            $reasonWhere = 'AND sc.name LIKE ?';
            $reasonParams[] = '%'.$q.'%';
        }

        $reasonSql = "
            SELECT scd.shipping_company_id AS id,
                GROUP_CONCAT(DISTINCT NULLIF(TRIM(n.note), '') SEPARATOR ' | ') AS refusal_reasons
            FROM shipping_company_details scd
            INNER JOIN shipping_companies sc ON sc.id = scd.shipping_company_id
            INNER JOIN notes n ON n.order_id = scd.order_id AND n.added_from = 'رفض استلام'
            WHERE scd.status = 'رفض استلام'
            AND DATE(COALESCE(NULLIF(scd.collect_date, '0000-00-00'), scd.created_at)) BETWEEN ? AND ?
            {$reasonWhere}
            GROUP BY scd.shipping_company_id
        ";

        $reasonRows = DB::select($reasonSql, $reasonParams);

        $reasonByCompany = [];
        foreach ($reasonRows as $r) {
            $reasonByCompany[(int) $r->id] = $r->refusal_reasons ?? '';
        }

        $data = [];
        foreach ($rows as $r) {
            $shipped = (int) $r->shipped_count;
            $collected = (int) $r->collected_count;
            $refused = (int) $r->refused_count;
            $pct = $shipped > 0
                ? round(100.0 * $collected / $shipped, 2)
                : 0.0;

            $data[] = [
                'id' => (int) $r->id,
                'name' => $r->name,
                'type' => $r->type,
                'orders_count' => $shipped,
                'collected_count' => $collected,
                'refused_count' => $refused,
                'collection_percentage' => $pct,
                'refusal_reasons' => $reasonByCompany[(int) $r->id] ?? '',
                'period_from' => $from,
                'period_to' => $to,
            ];
        }

        return response()->json([
            'data' => $data,
            'date_from' => $from,
            'date_to' => $to,
        ], 200);
    }
}
