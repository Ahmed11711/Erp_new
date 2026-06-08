<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\StockTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Server-rendered A4 printouts (signed URLs — safe to open from SPA print window).
 */
class PurchasePrintController extends Controller
{
    public function show(int $purchase)
    {
        $mainRow = Purchase::query()->with(['supplier:id,supplier_name'])->findOrFail($purchase);
        $mainId = $mainRow->ref ? (int) $mainRow->ref : (int) $mainRow->id;

        $latest = Purchase::query()
            ->where('ref', $mainId)
            ->with(['supplier:id,supplier_name'])
            ->latest('id')
            ->first();

        $invoice = $latest ?: Purchase::query()->with(['supplier:id,supplier_name'])->findOrFail($mainId);

        $lines = DB::table('invoice_categories')->where('purchase_id', $invoice->id)->orderBy('id')->get();

        $subtotal = (float) $lines->sum('total');
        $shipping = (float) ($invoice->shipping_total ?? $invoice->transport_cost ?? 0);
        $grand = $subtotal + $shipping;

        $company = config('app.name', 'Company');

        return response()->view('documents.purchase-print', [
            'invoice' => $invoice,
            'lines' => $lines,
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'grand' => $grand,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'company' => $company,
            'logoUrl' => asset('images/logo.png'),
        ]);
    }
}
