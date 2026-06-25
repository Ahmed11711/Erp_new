<?php

namespace App\Http\Controllers\Processing;

use App\Http\Controllers\Controller;
use App\Models\ProcessingInvoice;
use App\Models\ProcessingMaterialBalance;
use App\Models\ProcessingOrder;
use Illuminate\Support\Facades\DB;

class ProcessingDashboardController extends Controller
{
    public function kpis()
    {
        $openOrders = ProcessingOrder::query()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        $atVendor = (float) ProcessingMaterialBalance::query()->sum('qty_at_vendor');

        $outstandingAp = (float) ProcessingInvoice::query()
            ->whereIn('status', ['posted', 'partially_paid'])
            ->sum('due_amount');

        $overdue = ProcessingInvoice::query()
            ->whereIn('status', ['posted', 'partially_paid'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();

        return response()->json([
            'open_orders' => $openOrders,
            'qty_at_vendor' => $atVendor,
            'outstanding_ap' => round($outstandingAp, 2),
            'overdue_invoices' => $overdue,
        ]);
    }

    public function materialsAtVendor()
    {
        $rows = ProcessingMaterialBalance::query()
            ->with(['order:id,order_number', 'supplier:id,supplier_name'])
            ->where('qty_at_vendor', '>', 0)
            ->orderByDesc('qty_at_vendor')
            ->get();

        return response()->json($rows);
    }

    public function vendorBalances()
    {
        $rows = DB::table('processing_invoices as pi')
            ->join('suppliers as s', 's.id', '=', 'pi.supplier_id')
            ->whereNull('pi.deleted_at')
            ->whereIn('pi.status', ['posted', 'partially_paid'])
            ->groupBy('pi.supplier_id', 's.supplier_name', 's.balance')
            ->selectRaw('pi.supplier_id, s.supplier_name, s.balance as supplier_balance, SUM(pi.due_amount) as processing_due')
            ->get();

        return response()->json($rows);
    }

    public function aging()
    {
        $today = now()->toDateString();
        $rows = ProcessingInvoice::query()
            ->with('supplier:id,supplier_name')
            ->whereIn('status', ['posted', 'partially_paid'])
            ->where('due_amount', '>', 0)
            ->orderBy('due_date')
            ->get()
            ->map(function (ProcessingInvoice $inv) use ($today) {
                $due = $inv->due_date?->format('Y-m-d') ?? $today;
                $days = max(0, (int) ((strtotime($today) - strtotime($due)) / 86400));

                return [
                    'invoice' => $inv,
                    'days_overdue' => $days,
                    'bucket' => match (true) {
                        $days <= 30 => '0-30',
                        $days <= 60 => '31-60',
                        $days <= 90 => '61-90',
                        default => '90+',
                    },
                ];
            });

        return response()->json($rows);
    }
}
