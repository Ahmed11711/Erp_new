<?php

namespace App\Http\Controllers\Processing;

use App\Http\Controllers\Controller;
use App\Services\Processing\ProcessingWarehouseStockService;
use Illuminate\Http\Request;

class ProcessingWarehouseController extends Controller
{
    public function __construct(private ProcessingWarehouseStockService $warehouse)
    {
    }

    public function overview(Request $request)
    {
        return response()->json($this->warehouse->overview($this->filters($request)));
    }

    public function balances(Request $request)
    {
        return response()->json($this->warehouse->balances($this->filters($request)));
    }

    public function movements(Request $request)
    {
        $filters = $this->filters($request);
        $filters['limit'] = (int) $request->input('limit', 500);

        return response()->json($this->warehouse->movements($filters));
    }

    public function filterOptions()
    {
        return response()->json($this->warehouse->filterOptions());
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'supplier_id' => $request->input('supplier_id'),
            'category_id' => $request->input('category_id'),
            'processing_order_id' => $request->input('processing_order_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'search' => $request->input('q'),
        ];
    }
}
