<?php

namespace App\Http\Controllers;

use App\Enums\CollectionProviderType;
use App\Enums\OrderSettlementStatus;
use App\Models\CollectionCompany;
use App\Models\Order;
use App\Models\shippingCompanyDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CollectionAccountingReportController extends Controller
{
    /**
     * ملخص ذمم شركات التحصيل — المبلغ المطلوب تحصيله من كل شركة.
     * GET /api/reports/collection-accounts
     */
    public function accountsSummary(Request $request)
    {
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $status = $request->query('status'); // active | inactive

        $query = CollectionCompany::query()
            ->with(['linkedShippingCompany:id,name,balance', 'receivableTreeAccount:id,code,name']);

        if ($status) {
            $query->where('status', $status);
        }

        $companies = $query->orderBy('name')->get();
        $rows = [];

        foreach ($companies as $cc) {
            $rows[] = $this->buildCompanySummaryRow($cc, $dateFrom, $dateTo);
        }

        $collection = collect($rows);

        return response()->json([
            'data' => $rows,
            'summary' => [
                'total_companies' => $collection->count(),
                'total_pending_to_collect' => round($collection->sum('pending_to_collect'), 3),
                'total_collected' => round($collection->sum('total_collected'), 3),
                'total_orders_pending' => (int) $collection->sum('pending_orders_count'),
            ],
        ]);
    }

    /**
     * كشف تفصيلي لشركة تحصيل واحدة.
     * GET /api/reports/collection-accounts/{id}/statement
     */
    public function companyStatement(Request $request, int $id)
    {
        $company = CollectionCompany::with(['linkedShippingCompany', 'receivableTreeAccount'])->findOrFail($id);
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $source = $request->query('source', 'auto'); // auto | orders | ledger

        $useLedger = $company->linked_shipping_company_id
            && in_array($source, ['auto', 'ledger'], true);

        if ($useLedger) {
            return $this->ledgerStatement($request, $company, (int) $company->linked_shipping_company_id, $dateFrom, $dateTo);
        }

        return $this->ordersStatement($request, $company, $dateFrom, $dateTo);
    }

    /**
     * طلبات معلّقة لشركة تحصيل.
     * GET /api/reports/collection-accounts/pending-orders
     */
    public function pendingOrders(Request $request)
    {
        $companyId = (int) $request->query('company_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $cc = CollectionCompany::findOrFail($companyId);

        $query = $this->applyPendingCollectionScope(
            $this->ordersBaseQuery($cc->id, $cc->linked_shipping_company_id),
            $dateFrom,
            $dateTo
        );

        $orders = $query->orderByDesc('orders.id')
            ->paginate($request->integer('per_page', 50));

        return response()->json($orders);
    }

    private function buildCompanySummaryRow(CollectionCompany $cc, ?string $dateFrom, ?string $dateTo): array
    {
        $orderStats = $this->orderDetailsStats($cc->id, $dateFrom, $dateTo);
        $ledgerStats = $cc->linked_shipping_company_id
            ? $this->ledgerStats((int) $cc->linked_shipping_company_id, $dateFrom, $dateTo)
            : [
                'pending_to_collect' => 0,
                'total_collected' => 0,
                'pending_orders_count' => 0,
                'shipped_pending' => 0,
                'delivered_pending' => 0,
                'operational_balance' => 0,
            ];

        $pendingToCollect = $cc->linked_shipping_company_id
            ? max((float) $ledgerStats['pending_to_collect'], (float) $orderStats['pending_to_collect'])
            : (float) $orderStats['pending_to_collect'];

        return [
            'id' => $cc->id,
            'name' => $cc->name,
            'status' => $cc->status,
            'linked_shipping_company_id' => $cc->linked_shipping_company_id,
            'linked_shipping_company_name' => $cc->linkedShippingCompany?->name,
            'receivable_tree_account_id' => $cc->receivable_tree_account_id,
            'receivable_tree_account' => $cc->receivableTreeAccount,
            'pending_to_collect' => round($pendingToCollect, 3),
            'pending_from_orders' => round((float) $orderStats['pending_to_collect'], 3),
            'pending_from_ledger' => round((float) $ledgerStats['pending_to_collect'], 3),
            'total_collected' => round((float) ($ledgerStats['total_collected'] ?: $orderStats['total_collected']), 3),
            'pending_orders_count' => (int) max($orderStats['pending_orders_count'], $ledgerStats['pending_orders_count']),
            'shipped_pending' => round((float) $ledgerStats['shipped_pending'], 3),
            'delivered_pending' => round((float) $ledgerStats['delivered_pending'], 3),
            'operational_balance' => (float) ($ledgerStats['operational_balance'] ?? $cc->linkedShippingCompany?->balance ?? 0),
            'pending_order_ids' => $orderStats['pending_order_ids'],
            'data_source' => $cc->linked_shipping_company_id ? 'ledger_and_orders' : 'orders',
        ];
    }

    private function orderDetailsStats(int $collectionCompanyId, ?string $dateFrom, ?string $dateTo): array
    {
        $cc = CollectionCompany::find($collectionCompanyId);
        $linkedId = $cc?->linked_shipping_company_id;

        // المتبقّي مطلوب تحصيله = كل طلب بذمة تحصيل (collection_receivable_amount > 0) لم تُسوَّ بعد،
        // بصرف النظر عن حالة الشحن — لأن التحصيل الإلكتروني المسبق (Paymob/Sympl/Visa) يقع وقت الطلب.
        $settledStatus = OrderSettlementStatus::Settled->value;

        $pendingQ = $this->applyPendingCollectionScope(
            $this->ordersBaseQuery($collectionCompanyId, $linkedId),
            $dateFrom,
            $dateTo
        );

        $pendingRows = (clone $pendingQ)->get([
            'orders.id as order_id',
            DB::raw('COALESCE(order_details.collection_receivable_amount, 0) as coll_amt'),
        ]);

        $pendingOrderIds = [];
        $pendingSum = 0.0;
        foreach ($pendingRows as $row) {
            $amt = (float) $row->coll_amt;
            if ($amt <= 0.009) {
                continue;
            }
            $pendingSum += $amt;
            $pendingOrderIds[] = (int) $row->order_id;
        }

        $collectedDateExpr = "COALESCE(NULLIF(order_details.collection_date, '0000-00-00'), orders.order_date)";
        $collectedQ = $this->ordersBaseQuery($collectionCompanyId, $linkedId)
            ->where(function ($q) use ($settledStatus) {
                $q->where('orders.order_status', 'تم التحصيل')
                    ->orWhere('order_details.settlement_status', $settledStatus);
            });
        if ($dateFrom) {
            $collectedQ->whereRaw("DATE($collectedDateExpr) >= ?", [$dateFrom]);
        }
        if ($dateTo) {
            $collectedQ->whereRaw("DATE($collectedDateExpr) <= ?", [$dateTo]);
        }

        $collectedSum = (float) (clone $collectedQ)->sum(DB::raw('COALESCE(order_details.collection_receivable_amount, 0)'));

        return [
            'pending_to_collect' => $pendingSum,
            'total_collected' => $collectedSum,
            'pending_orders_count' => count(array_unique($pendingOrderIds)),
            'pending_order_ids' => array_values(array_unique($pendingOrderIds)),
        ];
    }

    private function ledgerStats(int $shippingCompanyId, ?string $dateFrom, ?string $dateTo): array
    {
        $base = shippingCompanyDetails::query()
            ->where('shipping_company_id', $shippingCompanyId);

        if ($dateFrom) {
            $base->whereDate('shipping_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $base->whereDate('shipping_date', '<=', $dateTo);
        }

        $pending = (clone $base)->where('is_done', 0)
            ->whereIn('status', ['تم شحن', 'تم التسليم']);

        $aggregates = (clone $base)->select([
            DB::raw('SUM(CASE WHEN is_done = 0 THEN amount ELSE 0 END) as pending_to_collect'),
            DB::raw('SUM(CASE WHEN is_done = 1 THEN ABS(amount) ELSE 0 END) as total_collected'),
            DB::raw('SUM(CASE WHEN status = "تم شحن" AND is_done = 0 THEN amount ELSE 0 END) as shipped_pending'),
            DB::raw('SUM(CASE WHEN status = "تم التسليم" AND is_done = 0 THEN amount ELSE 0 END) as delivered_pending'),
            DB::raw('COUNT(DISTINCT CASE WHEN is_done = 0 THEN order_id END) as pending_orders_count'),
        ])->first();

        $balance = \App\Models\ShippingCompany::find($shippingCompanyId)?->balance ?? 0;

        return [
            'pending_to_collect' => (float) ($aggregates->pending_to_collect ?? 0),
            'total_collected' => (float) ($aggregates->total_collected ?? 0),
            'pending_orders_count' => (int) ($aggregates->pending_orders_count ?? 0),
            'shipped_pending' => (float) ($aggregates->shipped_pending ?? 0),
            'delivered_pending' => (float) ($aggregates->delivered_pending ?? 0),
            'operational_balance' => (float) $balance,
        ];
    }

    /**
     * نطاق «المطلوب تحصيله»: ذمة تحصيل قائمة (> 0) لم تُسوَّ، لطلب غير ملغى/محصّل،
     * بصرف النظر عن حالة الشحن (يشمل التحصيل الإلكتروني المسبق من وقت الطلب).
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Query\Builder
     */
    private function applyPendingCollectionScope($query, ?string $dateFrom, ?string $dateTo)
    {
        $settledStatus = OrderSettlementStatus::Settled->value;
        $dateExpr = "COALESCE(NULLIF(order_details.shipping_date, '0000-00-00'), orders.order_date)";

        $query->whereRaw('COALESCE(order_details.collection_receivable_amount, 0) > 0.009')
            ->whereNotIn('orders.order_status', ['ملغي', 'تم التحصيل'])
            ->where(function ($q) use ($settledStatus) {
                $q->whereNull('order_details.settlement_status')
                    ->orWhere('order_details.settlement_status', '!=', $settledStatus);
            });

        if ($dateFrom) {
            $query->whereRaw("DATE($dateExpr) >= ?", [$dateFrom]);
        }
        if ($dateTo) {
            $query->whereRaw("DATE($dateExpr) <= ?", [$dateTo]);
        }

        return $query;
    }

    private function ordersBaseQuery(int $collectionCompanyId, ?int $linkedShippingCompanyId)
    {
        return DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->where(function ($q) use ($collectionCompanyId, $linkedShippingCompanyId) {
                $q->where(function ($q2) use ($collectionCompanyId) {
                    $q2->where('order_details.collection_provider_type', CollectionProviderType::CollectionCompany->value)
                        ->where('order_details.collection_provider_id', $collectionCompanyId);
                });
                if ($linkedShippingCompanyId) {
                    $q->orWhere('order_details.collection_company_id', $linkedShippingCompanyId);
                }
            });
    }

    private function ledgerStatement(Request $request, CollectionCompany $company, int $shippingCompanyId, ?string $dateFrom, ?string $dateTo)
    {
        $status = $request->query('status');
        $isDone = $request->query('is_done');

        $query = shippingCompanyDetails::where('shipping_company_id', $shippingCompanyId)
            ->with(['order:id,customer_name,customer_phone_1,net_total,order_status,prepaid_amount'])
            ->orderByDesc('id');

        if ($dateFrom) {
            $query->whereDate('shipping_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('shipping_date', '<=', $dateTo);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($isDone !== null && $isDone !== '') {
            $query->where('is_done', (int) $isDone);
        }

        $details = $query->paginate($request->integer('per_page', 50));

        $aggregates = shippingCompanyDetails::where('shipping_company_id', $shippingCompanyId)
            ->when($dateFrom, fn ($q) => $q->whereDate('shipping_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('shipping_date', '<=', $dateTo))
            ->select([
                DB::raw('SUM(CASE WHEN is_done = 0 THEN amount ELSE 0 END) as outstanding'),
                DB::raw('SUM(CASE WHEN is_done = 1 THEN ABS(amount) ELSE 0 END) as settled'),
                DB::raw('SUM(CASE WHEN status = "تم شحن" AND is_done = 0 THEN amount ELSE 0 END) as shipped_pending'),
                DB::raw('SUM(CASE WHEN status = "تم التسليم" AND is_done = 0 THEN amount ELSE 0 END) as delivered_pending'),
                DB::raw('COUNT(DISTINCT order_id) as total_orders'),
            ])
            ->first();

        return response()->json([
            'company' => $company,
            'source' => 'ledger',
            'aggregates' => $aggregates,
            'details' => $details,
        ]);
    }

    private function ordersStatement(Request $request, CollectionCompany $company, ?string $dateFrom, ?string $dateTo)
    {
        $query = $this->ordersBaseQuery($company->id, $company->linked_shipping_company_id)
            ->select([
                'orders.id',
                'orders.customer_name',
                'orders.customer_phone_1',
                'orders.net_total',
                'orders.prepaid_amount',
                'orders.order_status',
                'orders.order_date',
                'order_details.shipping_date',
                'order_details.collection_date',
                'order_details.collection_receivable_amount',
                'order_details.shipping_receivable_amount',
                'order_details.collection_status',
            ]);

        if ($dateFrom) {
            $query->whereDate('order_details.shipping_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('order_details.shipping_date', '<=', $dateTo);
        }

        if ($request->filled('order_status')) {
            $query->where('orders.order_status', $request->order_status);
        }

        $pendingSum = (float) $this->applyPendingCollectionScope(
            $this->ordersBaseQuery($company->id, $company->linked_shipping_company_id),
            $dateFrom,
            $dateTo
        )->sum(DB::raw('COALESCE(order_details.collection_receivable_amount, 0)'));

        $details = $query->orderByDesc('orders.id')
            ->paginate($request->integer('per_page', 50));

        return response()->json([
            'company' => $company,
            'source' => 'orders',
            'aggregates' => [
                'outstanding' => round($pendingSum, 3),
                'total_orders' => $details->total(),
            ],
            'details' => $details,
        ]);
    }
}
