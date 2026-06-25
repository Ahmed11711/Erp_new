<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\Purchase;
use App\Models\ShippingCompany;
use App\Models\shippingCompanyDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShippingAccountingReportController extends Controller
{
    /**
     * تقرير أرصدة المناديب وشركات الشحن وشركات التحصيل.
     * GET /api/reports/shipping-accounts
     */
    public function accountsSummary(Request $request)
    {
        $type = $request->query('type'); // مندوب | شركة | null (all)
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = ShippingCompany::query()
            ->select([
                'shipping_companies.id',
                'shipping_companies.name',
                'shipping_companies.type',
                'shipping_companies.balance',
                'shipping_companies.tree_account_id',
                'shipping_companies.receivable_tree_account_id',
            ])
            ->with([
                'receivableTreeAccount:id,balance',
                'treeAccount:id,balance',
            ])
            ->withCount([
                'details as total_orders' => function ($q) use ($dateFrom, $dateTo) {
                    $q->select(DB::raw('COUNT(DISTINCT order_id)'));
                    if ($dateFrom) $q->whereDate('shipping_date', '>=', $dateFrom);
                    if ($dateTo) $q->whereDate('shipping_date', '<=', $dateTo);
                },
                'details as pending_collection_count' => function ($q) use ($dateFrom, $dateTo) {
                    $q->where('is_done', 0)->where('status', 'تم شحن');
                    if ($dateFrom) $q->whereDate('shipping_date', '>=', $dateFrom);
                    if ($dateTo) $q->whereDate('shipping_date', '<=', $dateTo);
                },
                'details as delivered_pending_settlement' => function ($q) use ($dateFrom, $dateTo) {
                    $q->where('is_done', 0)->where('status', 'تم التسليم');
                    if ($dateFrom) $q->whereDate('shipping_date', '>=', $dateFrom);
                    if ($dateTo) $q->whereDate('shipping_date', '<=', $dateTo);
                },
            ])
            ->withSum([
                'details as total_shipped_amount' => function ($q) use ($dateFrom, $dateTo) {
                    $q->where('status', 'تم شحن');
                    if ($dateFrom) $q->whereDate('shipping_date', '>=', $dateFrom);
                    if ($dateTo) $q->whereDate('shipping_date', '<=', $dateTo);
                },
            ], 'amount')
            ->withSum([
                'details as total_collected_amount' => function ($q) use ($dateFrom, $dateTo) {
                    $q->where('status', 'تم التحصيل');
                    if ($dateFrom) $q->whereDate('shipping_date', '>=', $dateFrom);
                    if ($dateTo) $q->whereDate('shipping_date', '<=', $dateTo);
                },
            ], 'amount')
            ->withSum([
                'details as total_pending_amount' => function ($q) use ($dateFrom, $dateTo) {
                    $q->where('is_done', 0);
                    if ($dateFrom) $q->whereDate('shipping_date', '>=', $dateFrom);
                    if ($dateTo) $q->whereDate('shipping_date', '<=', $dateTo);
                },
            ], 'amount');

        if ($type) {
            $query->where('type', $type);
        }

        $companies = $query->orderBy('name')->get();
        $companies->each(function (ShippingCompany $company) {
            $company->balance = $company->displayBalance();
        });

        $companyIds = $companies->pluck('id')->all();
        if ($companyIds !== []) {
            $pendingOrderIdsByCompany = DB::table('shipping_company_details')
                ->whereIn('shipping_company_id', $companyIds)
                ->where('is_done', 0)
                ->whereIn('status', ['تم شحن', 'تم التسليم'])
                ->select(
                    'shipping_company_id',
                    DB::raw('GROUP_CONCAT(DISTINCT order_id ORDER BY order_id SEPARATOR ",") as pending_order_ids')
                )
                ->groupBy('shipping_company_id')
                ->pluck('pending_order_ids', 'shipping_company_id');

            foreach ($companies as $c) {
                $raw = $pendingOrderIdsByCompany[$c->id] ?? null;
                $c->pending_order_ids = $raw
                    ? array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $raw)))))
                    : [];
            }

            $freightStats = $this->purchaseFreightStatsByCompany($companyIds, $dateFrom, $dateTo);
            foreach ($companies as $c) {
                $st = $freightStats[(int) $c->id] ?? ['count' => 0, 'total' => 0.0];
                $c->purchase_freight_count = (int) $st['count'];
                $c->purchase_freight_total = round((float) $st['total'], 2);
            }
        }

        return response()->json([
            'data' => $companies,
            'summary' => [
                'total_companies' => $companies->count(),
                'total_pending_amount' => $companies->sum('total_pending_amount'),
                'total_shipped_amount' => $companies->sum('total_shipped_amount'),
                'total_collected_amount' => $companies->sum('total_collected_amount'),
            ],
        ]);
    }

    /**
     * كشف حساب مفصل لمندوب أو شركة شحن أو شركة تحصيل.
     * GET /api/reports/shipping-accounts/{id}/statement
     */
    public function companyStatement(Request $request, $id)
    {
        $company = ShippingCompany::findOrFail($id);
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $status = $request->query('status');
        $isDone = $request->query('is_done');

        $query = shippingCompanyDetails::where('shipping_company_id', $id)
            ->with(['order:id,customer_name,customer_phone_1,net_total,order_status,prepaid_amount'])
            ->orderByDesc('id');

        if ($dateFrom) $query->whereDate('shipping_date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('shipping_date', '<=', $dateTo);
        if ($status) $query->where('status', $status);
        if ($isDone !== null && $isDone !== '') $query->where('is_done', (int) $isDone);

        $details = $query->paginate($request->query('per_page', 50));

        $stmtOrderIds = $details->getCollection()->pluck('order_id')->unique()->filter()->values();
        foreach ($stmtOrderIds as $oid) {
            Order::reconcileCollectionStatusIfAllShippingLinesClosed((int) $oid);
        }
        $details->getCollection()->each(function ($row) {
            if ($row->relationLoaded('order') && $row->order) {
                $row->order->refresh();
            }
            $row->collectible = ! $row->is_done && in_array($row->status, ['تم شحن', 'تم التسليم'], true);
            $row->collectible_amount = $row->collectible ? round(abs((float) $row->amount), 2) : 0;
        });

        $aggregates = shippingCompanyDetails::where('shipping_company_id', $id)
            ->when($dateFrom, fn($q) => $q->whereDate('shipping_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('shipping_date', '<=', $dateTo))
            ->select([
                DB::raw('SUM(CASE WHEN is_done = 0 THEN amount ELSE 0 END) as outstanding'),
                DB::raw('SUM(CASE WHEN is_done = 1 THEN ABS(amount) ELSE 0 END) as settled'),
                DB::raw('SUM(CASE WHEN status = "تم شحن" AND is_done = 0 THEN amount ELSE 0 END) as shipped_pending'),
                DB::raw('SUM(CASE WHEN status = "تم التسليم" AND is_done = 0 THEN amount ELSE 0 END) as delivered_pending'),
                DB::raw('COUNT(DISTINCT order_id) as total_orders'),
            ])
            ->first();

        $purchaseFreight = $this->latestPurchaseFreightRows([(int) $id], $dateFrom, $dateTo);
        $aggregates->purchase_freight_total = round((float) $purchaseFreight->sum('transport_cost'), 2);
        $aggregates->purchase_freight_count = $purchaseFreight->count();

        return response()->json([
            'company' => $company,
            'aggregates' => $aggregates,
            'details' => $details,
            'purchase_freight' => $purchaseFreight->map(function (Purchase $p) {
                return [
                    'purchase_id' => $p->id,
                    'invoice_no' => $p->invoice_no ?? $p->invoice_number,
                    'receipt_date' => $p->receipt_date,
                    'supplier_name' => $p->supplier?->supplier_name,
                    'invoice_type' => $p->invoice_type,
                    'transport_cost' => round((float) $p->transport_cost, 2),
                ];
            })->values(),
        ]);
    }

    /**
     * @param  array<int>  $companyIds
     * @return array<int, array{count:int, total:float}>
     */
    private function purchaseFreightStatsByCompany(array $companyIds, ?string $dateFrom, ?string $dateTo): array
    {
        $stats = [];
        foreach ($this->latestPurchaseFreightRows($companyIds, $dateFrom, $dateTo) as $row) {
            $cid = (int) $row->shipping_company_id;
            if (! isset($stats[$cid])) {
                $stats[$cid] = ['count' => 0, 'total' => 0.0];
            }
            $stats[$cid]['count']++;
            $stats[$cid]['total'] += (float) $row->transport_cost;
        }

        return $stats;
    }

    /**
     * آخر مراجعة لكل فاتورة مشتريات لها شحن توريد على المندوب/الشركة.
     *
     * @param  array<int>  $companyIds
     * @return \Illuminate\Support\Collection<int, Purchase>
     */
    private function latestPurchaseFreightRows(array $companyIds, ?string $dateFrom, ?string $dateTo)
    {
        if ($companyIds === []) {
            return collect();
        }

        $rows = Purchase::query()
            ->select('id', 'ref', 'shipping_company_id', 'transport_cost', 'receipt_date', 'invoice_no', 'invoice_number', 'supplier_id', 'invoice_type')
            ->with('supplier:id,supplier_name')
            ->whereIn('shipping_company_id', $companyIds)
            ->where('transport_cost', '>', 0)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', '1');
            })
            ->when($dateFrom, fn ($q) => $q->whereDate('receipt_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('receipt_date', '<=', $dateTo))
            ->orderByDesc('id')
            ->get();

        return $rows
            ->groupBy(fn (Purchase $p) => (int) ($p->ref ?: $p->id))
            ->map(fn ($group) => $group->first())
            ->values();
    }

    /**
     * تقرير الطلبات المعلقة حسب الحالة للمناديب/الشركات.
     * GET /api/reports/shipping-accounts/pending-orders
     */
    public function pendingOrders(Request $request)
    {
        $companyId = $request->query('company_id');
        $companyType = $request->query('company_type');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $orderStatus = $request->query('order_status');

        $query = Order::query()
            ->join('order_details', 'orders.id', '=', 'order_details.order_id')
            ->leftJoin('shipping_companies as sc', 'order_details.shipping_company_id', '=', 'sc.id')
            ->leftJoin('shipping_companies as cc', 'order_details.collection_company_id', '=', 'cc.id')
            ->select([
                'orders.id',
                'orders.customer_name',
                'orders.customer_phone_1',
                'orders.net_total',
                'orders.prepaid_amount',
                'orders.order_status',
                'orders.order_date',
                'orders.shipping_cost',
                'order_details.shipping_date',
                'order_details.delivery_date',
                'order_details.collection_date',
                'order_details.shipping_company_id',
                'order_details.collection_company_id',
                'order_details.shipping_receivable_amount',
                'order_details.collection_receivable_amount',
                'sc.name as shipping_company_name',
                'sc.type as shipping_company_type',
                'cc.name as collection_company_name',
            ])
            ->whereIn('orders.order_status', ['تم شحن', 'تم التسليم', 'شحن جزئي']);

        if ($companyId) {
            $query->where(function ($q) use ($companyId) {
                $q->where('order_details.shipping_company_id', $companyId)
                  ->orWhere('order_details.collection_company_id', $companyId);
            });
        }

        if ($companyType) {
            $query->where('sc.type', $companyType);
        }

        if ($dateFrom) $query->whereDate('order_details.shipping_date', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('order_details.shipping_date', '<=', $dateTo);
        if ($orderStatus) $query->where('orders.order_status', $orderStatus);

        $orders = $query->orderByDesc('orders.id')
            ->paginate($request->query('per_page', 50));

        return response()->json($orders);
    }

    /**
     * ملخص محاسبي للتسوية — كم لكل مندوب/شركة.
     * GET /api/reports/shipping-accounts/settlement-summary
     */
    public function settlementSummary(Request $request)
    {
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $type = $request->query('type');

        $query = DB::table('shipping_company_details as scd')
            ->join('shipping_companies as sc', 'scd.shipping_company_id', '=', 'sc.id')
            ->where('scd.is_done', 0)
            ->select([
                'sc.id',
                'sc.name',
                'sc.type',
                DB::raw('COUNT(DISTINCT scd.order_id) as orders_count'),
                DB::raw('SUM(scd.amount) as total_outstanding'),
                DB::raw('SUM(CASE WHEN scd.status = "تم شحن" THEN scd.amount ELSE 0 END) as shipped_amount'),
                DB::raw('SUM(CASE WHEN scd.status = "تم التسليم" THEN scd.amount ELSE 0 END) as delivered_amount'),
                DB::raw('MIN(scd.shipping_date) as oldest_shipment'),
                DB::raw('MAX(scd.shipping_date) as latest_shipment'),
            ])
            ->groupBy('sc.id', 'sc.name', 'sc.type');

        if ($dateFrom) $query->where('scd.shipping_date', '>=', $dateFrom);
        if ($dateTo) $query->where('scd.shipping_date', '<=', $dateTo);
        if ($type) $query->where('sc.type', $type);

        $results = $query->orderByDesc('total_outstanding')->get();

        return response()->json([
            'data' => $results,
            'totals' => [
                'total_outstanding' => $results->sum('total_outstanding'),
                'total_orders' => $results->sum('orders_count'),
            ],
        ]);
    }
}
