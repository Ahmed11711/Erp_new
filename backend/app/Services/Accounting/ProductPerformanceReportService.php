<?php

namespace App\Services\Accounting;

use App\Models\AccountEntry;
use App\Services\CategoryInventoryCostService;
use Illuminate\Support\Facades\DB;

/**
 * نفس منطق تقرير أداء المنتجات (مبيعات من شحن طلب، مرتجعات من رفض استلام، تخصيص تكلفة البضاعة).
 */
class ProductPerformanceReportService
{
    /**
     * @return array{date_from: string, date_to: string, totals: array, data: array<int, array>, by_category_id: array<int, array>}
     */
    public function computeForPeriod(string $start, string $end): array
    {
        $withinPeriod = function ($q) use ($start, $end) {
            return $q->where('created_at', '>=', $start . ' 00:00:00')
                ->where('created_at', '<=', $end . ' 23:59:59');
        };

        $shipments = DB::table('categories_balance')
            ->select('invoice_number', 'category_id',
                DB::raw('SUM(quantity) as qty'),
                DB::raw('SUM(total_price) as amount'))
            ->where('type', 'شحن طلب')
            ->when(true, $withinPeriod)
            ->groupBy('invoice_number', 'category_id')
            ->get();

        $returns = DB::table('categories_balance')
            ->select('category_id',
                DB::raw('SUM(quantity) as qty'),
                DB::raw('SUM(total_price) as amount'))
            ->where('type', 'رفض استلام طلب')
            ->when(true, $withinPeriod)
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');

        $salesByCategory = [];
        foreach ($shipments as $row) {
            $cid = (int) $row->category_id;
            if (! isset($salesByCategory[$cid])) {
                $salesByCategory[$cid] = ['qty' => 0.0, 'amount' => 0.0];
            }
            $salesByCategory[$cid]['qty'] += (float) $row->qty;
            $salesByCategory[$cid]['amount'] += (float) $row->amount;
        }

        $wrAvgSubQuery = function () {
            return DB::table('warehouse_ratings')
                ->select('category_id', DB::raw('SUM(quantity * price) / NULLIF(SUM(quantity), 0) as wr_avg'))
                ->where('quantity', '>', 0)
                ->groupBy('category_id');
        };

        $costCase = 'CASE WHEN c.quantity > 0.0000001 AND IFNULL(c.total_price,0) != 0 THEN c.total_price / c.quantity WHEN IFNULL(c.unit_price,0) > 0.0000001 THEN c.unit_price WHEN IFNULL(wr.wr_avg,0) > 0.0000001 THEN wr.wr_avg ELSE 0 END';

        $shipCogsRows = DB::table('categories_balance as cb')
            ->join('categories as c', 'c.id', '=', 'cb.category_id')
            ->leftJoinSub($wrAvgSubQuery(), 'wr', function ($join) {
                $join->on('wr.category_id', '=', 'c.id');
            })
            ->where('cb.type', 'شحن طلب')
            ->where('cb.created_at', '>=', $start . ' 00:00:00')
            ->where('cb.created_at', '<=', $end . ' 23:59:59')
            ->groupBy('cb.category_id')
            ->select('cb.category_id', DB::raw("SUM(COALESCE(cb.cost_total, cb.quantity * ({$costCase}))) as cogs"))
            ->get()
            ->keyBy('category_id');

        $retCogsRows = DB::table('categories_balance as cb')
            ->join('categories as c', 'c.id', '=', 'cb.category_id')
            ->leftJoinSub($wrAvgSubQuery(), 'wr', function ($join) {
                $join->on('wr.category_id', '=', 'c.id');
            })
            ->where('cb.type', 'رفض استلام طلب')
            ->where('cb.created_at', '>=', $start . ' 00:00:00')
            ->where('cb.created_at', '<=', $end . ' 23:59:59')
            ->groupBy('cb.category_id')
            ->select('cb.category_id', DB::raw("SUM(COALESCE(cb.cost_total, cb.quantity * ({$costCase}))) as cogs"))
            ->get()
            ->keyBy('category_id');

        $allocatedCogsFromMovements = [];
        $movementKeys = array_unique(array_merge(
            array_keys($shipCogsRows->toArray()),
            array_keys($retCogsRows->toArray())
        ));
        foreach ($movementKeys as $cid) {
            $cid = (int) $cid;
            $ship = (float) (optional($shipCogsRows->get($cid))->cogs ?? 0);
            $ret = (float) (optional($retCogsRows->get($cid))->cogs ?? 0);
            $allocatedCogsFromMovements[$cid] = $ship - $ret;
        }

        $cogsByOrder = [];
        $cogsEntries = AccountEntry::select('description', 'debit', 'credit', 'created_at')
            ->when(true, $withinPeriod)
            ->get();

        foreach ($cogsEntries as $entry) {
            if (! $entry->description) {
                continue;
            }
            if (mb_strpos($entry->description, 'تكلفة البضاعة المباعة للطلب رقم') !== false) {
                if (preg_match('/الطلب رقم\s+(\d+)/u', $entry->description, $m)) {
                    $orderId = $m[1];
                    $amount = (float) $entry->debit - (float) $entry->credit;
                    $cogsByOrder[$orderId] = ($cogsByOrder[$orderId] ?? 0.0) + $amount;
                }
            }
        }

        $salesByOrder = [];
        foreach ($shipments as $row) {
            $orderId = $row->invoice_number;
            $salesByOrder[$orderId] = ($salesByOrder[$orderId] ?? 0.0) + (float) $row->amount;
        }

        $allocatedCogsFromGl = [];
        foreach ($shipments as $row) {
            $orderId = $row->invoice_number;
            $orderCogs = $cogsByOrder[$orderId] ?? 0.0;
            $orderSales = $salesByOrder[$orderId] ?? 0.0;
            if ($orderCogs <= 0 || $orderSales <= 0) {
                continue;
            }

            $cid = (int) $row->category_id;
            $weight = (float) $row->amount / $orderSales;
            $allocatedCogsFromGl[$cid] = ($allocatedCogsFromGl[$cid] ?? 0.0) + ($orderCogs * $weight);
        }

        $allocatedCogsFallback = [];
        foreach ($salesByCategory as $cid => $salesData) {
            $cid = (int) $cid;
            $salesQty = (float) ($salesData['qty'] ?? 0);
            $retQty = (float) (optional($returns->get($cid))->qty ?? 0);
            $netQty = max(0, $salesQty - $retQty);
            if ($netQty <= 0) {
                continue;
            }

            $avgCost = CategoryInventoryCostService::resolveReferenceUnitCost($cid);
            $allocatedCogsFallback[$cid] = $netQty * $avgCost;
        }

        $allocatedCogs = [];
        $mergeIds = array_unique(array_merge(
            array_keys($salesByCategory),
            array_keys($returns->toArray()),
            array_keys($allocatedCogsFromMovements),
            array_keys($allocatedCogsFromGl),
            array_keys($allocatedCogsFallback)
        ));
        foreach ($mergeIds as $cid) {
            $cid = (int) $cid;
            $fromMove = $allocatedCogsFromMovements[$cid] ?? 0.0;
            $fromGl = $allocatedCogsFromGl[$cid] ?? 0.0;
            $fromFb = $allocatedCogsFallback[$cid] ?? 0.0;

            if (abs($fromMove) >= 0.000001) {
                $allocatedCogs[$cid] = $fromMove;
            } elseif (abs($fromGl) >= 0.000001) {
                $allocatedCogs[$cid] = $fromGl;
            } else {
                $allocatedCogs[$cid] = $fromFb;
            }
        }

        $categoryNames = DB::table('categories')->select('id', 'category_name')->get()->keyBy('id');

        $productRows = [];
        $categoryIds = array_unique(array_merge(array_keys($salesByCategory), array_keys($returns->toArray()), array_keys($allocatedCogs)));
        foreach ($categoryIds as $cid) {
            $cid = (int) $cid;
            $sales = $salesByCategory[$cid]['amount'] ?? 0.0;
            $salesQty = $salesByCategory[$cid]['qty'] ?? 0.0;
            $ret = $returns->get($cid) ?? $returns->get((string) $cid);
            $retQty = $ret->qty ?? 0.0;
            $retAmount = $ret->amount ?? 0.0;
            $netSales = $sales - $retAmount;
            $cogs = $allocatedCogs[$cid] ?? 0.0;
            $gross = $netSales - $cogs;
            $name = optional($categoryNames->get($cid))->category_name ?? ('#' . $cid);

            $refUnitCost = CategoryInventoryCostService::resolveReferenceUnitCost($cid);
            $netQtyForCost = max(0, $salesQty - $retQty);
            $avgUnitCost = $netQtyForCost > 0.000001 ? round($cogs / $netQtyForCost, 2) : 0.0;

            $productRows[] = [
                'category_id' => $cid,
                'category_name' => $name,
                'sales_qty' => round($salesQty, 3),
                'sales_amount' => round($sales, 2),
                'returns_qty' => round($retQty, 3),
                'returns_amount' => round($retAmount, 2),
                'net_sales' => round($netSales, 2),
                'cogs' => round($cogs, 2),
                'avg_unit_cost' => $avgUnitCost,
                'ref_unit_cost' => round($refUnitCost, 2),
                'gross_profit' => round($gross, 2),
                'gross_margin_percent' => $netSales != 0 ? round(($gross / $netSales) * 100, 2) : 0,
            ];
        }

        $sumNetQtyCost = 0.0;
        foreach ($productRows as $pr) {
            $nq = max(0, (float) ($pr['sales_qty'] ?? 0) - (float) ($pr['returns_qty'] ?? 0));
            $sumNetQtyCost += $nq;
        }
        $totals = [
            'sales_qty' => round(array_sum(array_column($productRows, 'sales_qty')), 3),
            'sales_amount' => round(array_sum(array_column($productRows, 'sales_amount')), 2),
            'returns_qty' => round(array_sum(array_column($productRows, 'returns_qty')), 3),
            'returns_amount' => round(array_sum(array_column($productRows, 'returns_amount')), 2),
            'net_sales' => round(array_sum(array_column($productRows, 'net_sales')), 2),
            'cogs' => round(array_sum(array_column($productRows, 'cogs')), 2),
            'avg_unit_cost' => $sumNetQtyCost > 0.000001
                ? round(array_sum(array_column($productRows, 'cogs')) / $sumNetQtyCost, 2)
                : 0,
            'gross_profit' => round(array_sum(array_column($productRows, 'gross_profit')), 2),
            'gross_margin_percent' => 0,
        ];
        $totals['gross_margin_percent'] = $totals['net_sales'] != 0
            ? round(($totals['gross_profit'] / $totals['net_sales']) * 100, 2)
            : 0;

        usort($productRows, fn ($a, $b) => $b['net_sales'] <=> $a['net_sales']);

        $byCategoryId = [];
        foreach ($productRows as $row) {
            $byCategoryId[(int) $row['category_id']] = $row;
        }

        return [
            'date_from' => $start,
            'date_to' => $end,
            'totals' => $totals,
            'data' => $productRows,
            'by_category_id' => $byCategoryId,
        ];
    }
}
