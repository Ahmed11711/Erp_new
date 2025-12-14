<?php

namespace App\Http\Controllers;

use App\Models\Approvals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Purchase;
use App\Models\PurchasesTracking;
use App\Models\Bank;
use App\Models\Supplier;


class ApprovalsController extends Controller
{

    public function index(Request $request){
        $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 10;
        $data = Approvals::query();
        if ($request->has('type')) {
            $data = $data->where('type' , $request->type);
        }
        if ($request->has('status')) {
            $data = $data->where('status' , $request->status);
        }
        if ($request->has('table_name')) {
            $data = $data->where('table_name' , $request->table_name);
        }
        if ($request->has('date')) {
            $data = $data->whereDate('created_at', 'like', $request->date . '%');
        }
        $data = $data->with('user')->orderBy('id' , 'desc')->paginate($itemsPerPage);
        return response()->json($data);
    }

    public function store(Request $request)
    {
        $request->validate([
            "id" => "required|exists:approvals,id",
            'status' => 'required|in:approved,rejected',
        ]);

        DB::beginTransaction();

        try {
            $data = Approvals::find($request->id);
            if($data->status !== 'pending'){
                return response()->json(['message' => 'Approval is not pending'], 422);
            }
            $data->status = $request->status;
            $data->save();

            if ($request->status === 'approved' && isset($data->column_values['id'])) {

                if($data->table_name == 'purchases' && $data->type == 'delete'){

                    $mainInvoice = Purchase::where('invoice_number' , $data->column_values['invoice_number'])->first();

                    $purchase = Purchase::where('invoice_number' , $mainInvoice->invoice_number)->latest('id')->first();

                    $oldCategories = DB::table('invoice_categories')->where('purchase_id', $purchase->id)->get();

                    foreach($oldCategories as $product){
                        DB::table('categories')->where('category_name', $product->product_name)->increment('quantity', $product->product_quantity*-1);
                        DB::table('categories')->where('category_name', $product->product_name)->increment('total_price', $product->total*-1);

                        DB::table('categories_balance')->insert([
                            'invoice_number' => $purchase->invoice_number,
                            'category_id' => DB::table('categories')->where('category_name', $product->product_name)->value('id'),
                            'type' => 'حذف فواتير مشتريات',
                            'quantity' => $product->product_quantity*-1,
                            'balance_before' => DB::table('categories')->where('category_name', $product->product_name)->value('quantity')- ($product->product_quantity*-1),
                            'balance_after' => DB::table('categories')->where('category_name', $product->product_name)->value('quantity'),
                            'price' => $product->product_price*-1,
                            'total_price' => $product->total*-1,
                            'by' => auth()->user()->name,
                            'created_at' =>now()
                        ]
                        );


                        DB::table('warehouse_ratings')->insert([
                            'category_id' => DB::table('categories')->where('category_name', $product->product_name)->value('id'),
                            'price' => $product->product_price*-1,
                            'quantity' => $product->product_quantity*-1,
                            'ref' => $purchase->invoice_number,
                            'invoice_id' => $purchase->id,
                            'fixed_quantity' => $product->product_quantity*-1,
                            'created_at' =>now()
                        ]);
                    }

                    $mainPurchase = Purchase::where('invoice_number' , $data->column_values['invoice_number'])->first();
                    $mainPurchase->status = '1';
                    $mainPurchase->save();

                    PurchasesTracking::create([
                        'invoice_id' => $mainPurchase->id,
                        'invoice_number' => $purchase->id,
                        'action' => 'حذف فاتورة',
                        'user_id' => $data->user_id,
                    ]);

                    $supplier = Supplier::find($purchase->supplier_id);
                    $supplier->last_balance = $supplier->balance;
                    $supplier->balance -= $purchase->due_amount;
                    $supplier->save();


                    DB::table('supplier_balance')->insert([
                        'invoice_id' => $purchase->id,
                        'balance_before' => $supplier->last_balance,
                        'balance_after' => $supplier->balance,
                        'user_id'=> $data->user_id
                    ]);

                    $bank = Bank::find($purchase->bank_id);
                    $paid = (double)$purchase->paid_amount;
                    $balance =(double) $bank->balance;
                    $bank->balance= $balance+ $paid;
                    $bank->save();

                    $bank_details = ' حذف فاتور رقم '.$purchase->invoice_number;

                    DB::table('bank_details')->insert([
                        'bank_id' => $purchase->bank_id,
                        'details' => $bank_details,
                        'ref' => $purchase->invoice_number,
                        'type' => 'فواتير مشتريات',
                        'amount' => (double)$paid ,
                        'balance_before' => $balance,
                        'balance_after' => $bank->balance,
                        'date' => date('Y-m-d'),
                        'created_at' => now(),
                        'user_id'=> $data->user_id
                    ]);

                } else {
                    $columnValues = $data->column_values;
                    $id = $columnValues['id'];
                    unset($columnValues['id']);

                    DB::table($data->table_name)->where('id', $id)->update($columnValues);
                }
            }

            DB::commit();
            return response()->json(['message' => 'success'], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to process the request', 'error' => $e->getMessage()], 500);
        }
    }

}
