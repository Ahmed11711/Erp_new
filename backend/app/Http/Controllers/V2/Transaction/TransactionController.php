<?php

namespace App\Http\Controllers\V2\Transaction;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{

  public function allTransaction(Request $request)
  {

  }
    public function index(Request $request)
    {
      $phone=$request->query('customer');
       $transaction=Transaction::where('phone',$phone)->orderBy('order_id', 'asc') ->paginate();
      return response()->json($transaction, 200);

    }

    public function store($data)
    {
      $transaction=Transaction::create($data);
    }
}
