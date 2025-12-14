<?php

namespace App\Http\Controllers\V2\Report\ReportOrder;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\AccountEntry;
use App\Repositories\AccountTree\AccountTreeRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Log;

class ReportOrderController extends Controller
{
    use ApiResponseTrait;
    public function __construct(public AccountTreeRepositoryInterface $repo)
    {
    }

    public function AllOrder(Request $request)
    {
     $report = DB::table('account_entries as ae')
    ->join('orders as o', 'o.id', '=', 'ae.order_id')
    ->select(
        'ae.order_id',
        'ae.entry_batch_code', 
        'o.customer_name',
        DB::raw('SUM(ae.debit) as total_debit'),
        DB::raw('SUM(ae.credit) as total_credit')
    )
    ->whereNotNull('ae.order_id')
    ->groupBy('ae.order_id', 'ae.entry_batch_code', 'o.customer_name')
    ->orderByDesc('ae.order_id')
    ->get();

    return $this->successResponse($report);

    }

public function getByOrderId(Request $request)
{
    $orderId = $request->query('order_id');
    $assetId = $request->query('asset_id');

    Log::alert('orderId',[$orderId]);
    Log::alert('asser',[$orderId]);

    $query = AccountEntry::query()->with('assets:id,name,code');

    if ($orderId) {
        $query->where('order_id', $orderId);
    } elseif ($assetId) {
        $query->where('tree_account_id', $assetId); // assuming asset_id refers to tree_account_id
    } else {
        return $this->errorResponse('Please provide either order_id or asset_id', 422);
    }

    $entries = $query->get();

    return $this->successResponse($entries);
}

}
