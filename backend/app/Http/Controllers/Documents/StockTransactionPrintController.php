<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\StockTransaction;

class StockTransactionPrintController extends Controller
{
    public function show(int $stockTransaction)
    {
        $doc = StockTransaction::query()
            ->with(['items.product:id,category_name', 'items.toProduct:id,category_name', 'type', 'warehouse:id,name'])
            ->findOrFail($stockTransaction);

        $company = config('app.name', 'Company');

        return response()->view('documents.stock-transaction-print', [
            'doc' => $doc,
            'company' => $company,
            'logoUrl' => asset('images/logo.png'),
        ]);
    }
}
