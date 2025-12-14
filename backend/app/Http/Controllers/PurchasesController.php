<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Purchase;
use App\Models\PurchasesTracking;
use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Models\Approvals;
use Illuminate\Support\Facades\DB;
use Validator;
class PurchasesController extends Controller
{
    //

    public function index()
    {
        $purchases = Purchase::with(['supplier' => function ($query) {
            $query->select('id', 'supplier_name');
        }])->get();
        return response()->json($purchases, 200);
    }

    public function search(Request $request){

        $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 10;
        $search = Purchase::query()->whereNull('ref');
        if($request->has('receipt_date')){
            $search->where('receipt_date', $request->receipt_date);
        }
        if($request->has('invoice_type')){
            $search->where('invoice_type', $request->invoice_type);
        }
        if($request->has('supplier_id')){
            $search->where('supplier_id', $request->supplier_id);
        }
        $search = $search->with([
            'supplier:id,supplier_name',
            'updatedPurchase'
        ])->orderBy('id', 'desc')->paginate($itemsPerPage);
        return response()->json($search, 200);
    }

    public function show($id, Request $request)
    {

        if($request->query('foredit') == 'true'){
            $invoice = Purchase::where('id', $id)->whereNull('ref')
            ->with(['bank:id,name', 'supplier:id,supplier_name'])
            ->first();

            if (!$invoice) {
                return response()->json(['error' => 'Invoice not found'], 404);
            }

            $latestPurchase = Purchase::where('ref', $id)
                ->with(['bank:id,name', 'supplier:id,supplier_name'])
                ->latest('id')
                ->first();

            if ($latestPurchase) {
                $latestPurchase->invoice_number = 'PO' . $latestPurchase->id;
                $categories = DB::table('invoice_categories')->where('purchase_id', $latestPurchase->id)->get();
                $invoice = $latestPurchase;
            } else {
                $categories = DB::table('invoice_categories')->where('purchase_id', $id)->get();
            }

            return response()->json([
                'invoice' => $invoice,
                'categories' => $categories
            ], 200);
        }

        $invoice = Purchase::where('id', $id)
            ->with(['bank:id,name', 'supplier:id,supplier_name'])
            ->first();

        $categories = DB::table('invoice_categories')->where('purchase_id', $id)->get();

        $tracking = PurchasesTracking::where('invoice_id', $id)->with(['user:id,name'])->get();

        if (!$invoice) {
            return response()->json(['error' => 'Invoice not found'], 404);
        }

        $data =[
            'invoice'=> $invoice,
            'tracking'=> $tracking,
            'categories'=> $categories
        ];

        return response()->json($data, 200);
    }



    public function store(Request $request)
    {
        // return $request;

         Validator::make($request->all(), [
            'supplier_id' => 'required',
            'invoice_type' => 'required',
            'receipt_date' => 'required',
            'total_price' => 'required',
            'paid_amount' => 'required',
            'due_amount' => 'required',
            'transport_cost' => 'required',
            'price_edited' => 'required',
            // 'invoice_image' => 'required',
            'bank_id'=>'required',
            'products' => 'required',
            'products.*.product_name' => 'required|string',
            'products.*.product_unit' => 'required|string',
            'products.*.product_quantity' => 'required|numeric',
            'products.*.product_price' => 'required|numeric',
            'products.*.total' => 'required|numeric',
            'products.*.price_edited' => 'required|boolean',
        ])->validate();

        $img_name ='';
        if($request->hasFile('invoice_image')){
            $img = $request->file('invoice_image');
            $img_name = time() . '.' . $img->extension();
            $img->move(public_path('images'), $img_name);
        }
        $purchase['invoice_image'] = $img_name;

        $old_paid_amount = 0;
        $old_due_amount = 0;
        $ref = null;
        $status = null;
        if($request->has('invoiceId')){
            $mainInvoice = Purchase::find($request->input('invoiceId'));

            $oldInvice = Purchase::where('invoice_number' , $mainInvoice->invoice_number)->latest('id')->first();

            $oldCategories = DB::table('invoice_categories')->where('purchase_id', $oldInvice->id)->get();
            foreach($oldCategories as $product){
                DB::table('categories')->where('category_name', $product->product_name)->increment('quantity', $product->product_quantity*-1);
                DB::table('categories')->where('category_name', $product->product_name)->increment('total_price', $product->total*-1);

                DB::table('categories_balance')->insert([
                    'invoice_number' => $oldInvice->invoice_number,
                    'category_id' => DB::table('categories')->where('category_name', $product->product_name)->value('id'),
                    'type' => 'تعديل فواتير مشتريات',
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
                    'ref' => $oldInvice->invoice_number,
                    'invoice_id' => $oldInvice->id,
                    'fixed_quantity' => $product->product_quantity*-1,
                    'created_at' =>now()
                ]);
            }
            $old_paid_amount = $oldInvice->paid_amount;
            $old_due_amount = $oldInvice->due_amount;
            $status = '0';
        }
        $purchase = Purchase::create(
            [
                'supplier_id' => request('supplier_id'),
                'invoice_type' => request('invoice_type'),
                'receipt_date' => request('receipt_date'),
                'total_price' => request('total_price'),
                'paid_amount' => request('paid_amount'),
                'due_amount' => request('due_amount'),
                'transport_cost' => request('transport_cost'),
                'price_edited' => request('price_edited'),
                'invoice_image' => $img_name,
                'bank_id'=>request('bank_id'),
                'status' => $status,
            ]
        );
        if($request->has('invoiceId')){
            $mainInvoice->status = '0';
            $mainInvoice->edits = $mainInvoice->edits + 1;
            $mainInvoice->save();
            PurchasesTracking::create([
                'invoice_id' => $mainInvoice->id,
                'invoice_number' => $purchase->id,
                'action' => 'تعديل فاتورة',
                'user_id' => auth()->id(),
            ]);
            $purchase->ref = $mainInvoice->id;
            $purchase->invoice_number = $mainInvoice->invoice_number;
            $purchase->save();
        }
        $products = $request->products;
        $products = json_decode($products, true);
        foreach($products as $product){
            DB::table('invoice_categories')->insert([
                'purchase_id' => $purchase->id,
                'product_name' => $product['product_name'],
                'product_quantity' => $product['product_quantity'],
                'product_unit' => $product['product_unit'],
                'product_price' => $product['product_price'],
                'total' => $product['total'],
                'price_edited' => $product['price_edited'],
            ]);

            DB::table('categories')->where('category_name', $product['product_name'])->increment('quantity', $product['product_quantity']);
            DB::table('categories')->where('category_name', $product['product_name'])->increment('total_price', $product['total']);
            // DB::table('categories')->where('category_name', $product['product_name'])->increment('initial_balance', $product['product_quantity']);

            DB::table('categories_balance')->insert([
                'invoice_number' => $purchase->invoice_number,
                'category_id' => DB::table('categories')->where('category_name', $product['product_name'])->value('id'),
                'type' => 'فواتير مشتريات',
                'quantity' => $product['product_quantity'],
                'balance_before' => DB::table('categories')->where('category_name', $product['product_name'])->value('quantity')- $product['product_quantity'],
                'balance_after' => DB::table('categories')->where('category_name', $product['product_name'])->value('quantity'),
                'price' => $product['product_price'],
                'total_price' => $product['total'],
                'by' => auth()->user()->name,
                'created_at' =>now()
            ]
            );


            DB::table('warehouse_ratings')->insert([
                'category_id' => DB::table('categories')->where('category_name', $product['product_name'])->value('id'),
                'price' => $product['product_price'],
                'quantity' => $product['product_quantity'],
                'ref' => $purchase->invoice_number,
                'invoice_id' => $purchase->id,
                'fixed_quantity' => $product['product_quantity'],
                'created_at' =>now()
            ]);
        }
        $supplier = Supplier::find($purchase->supplier_id);
        $supplier->last_balance = $supplier->balance;
        $supplier->balance += $purchase->due_amount - $old_due_amount;
        $supplier->save();


        DB::table('supplier_balance')->insert([
            'invoice_id' => $purchase->id,
            'balance_before' => $supplier->last_balance,
            'balance_after' => $supplier->balance,
            'user_id'=> auth()->user()->id
        ]);

        $bank = Bank::find($request->bank_id);
        $paid = (double)$request->paid_amount - (double)$old_paid_amount;
        $balance =(double) $bank->balance;
        $bank->balance= $balance- $paid;
        $bank->save();

        $bank_details = ' سداد المورد '.$supplier->supplier_name;
        if ($request->has('invoiceId')) {
            $bank_details = ' سداد المورد '.$supplier->supplier_name.' من تعديل فاتور رقم '.$oldInvice->invoice_number;
        }

        DB::table('bank_details')->insert([
            'bank_id' => $request->bank_id,
            'details' => $bank_details,
            'ref' => $purchase->invoice_number,
            'type' => 'فواتير مشتريات',
            'amount' => (double)$paid ,
            'balance_before' => $balance,
            'balance_after' => $bank->balance,
            'date' => date('Y-m-d'),
            'created_at' => now(),
            'user_id'=> auth()->user()->id
        ]);

        if (!($request->has('invoiceId'))) {
            PurchasesTracking::create([
                'invoice_id' => $purchase->id,
                'invoice_number' => $purchase->id,
                'action' => 'فاتورة جديدة',
                'user_id' => auth()->id(),
            ]);
        }
        return response()->json(['success' => true], 200);
    }






    public function destroy($id)
    {
        $mainInvoice = Purchase::find($id);
        $purchase = Purchase::where('invoice_number' , $mainInvoice->invoice_number)->with(['bank:id,name', 'supplier:id,supplier_name'])->latest('id')->first();
        if (auth()->user()->department != 'Admin') {
            $isExist = Approvals::where('column_values' , $purchase)->first();
            if($isExist){
                return response()->json($isExist, 422);
            }
            $appData = [
                'type' => 'delete',
                'table_name' => 'purchases',
                'column_values' => $purchase,
                'details' => $purchase,
                'user_id' => auth()->user()->id,
            ];
            $approval = Approvals::create($appData);
            return response()->json($approval, 201);
        }
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

        $mainPurchase = Purchase::find($id);
        $mainPurchase->status = '1';
        $mainPurchase->save();

        PurchasesTracking::create([
            'invoice_id' => $mainPurchase->id,
            'invoice_number' => $purchase->id,
            'action' => 'حذف فاتورة',
            'user_id' => auth()->id(),
        ]);

        $supplier = Supplier::find($purchase->supplier_id);
        $supplier->last_balance = $supplier->balance;
        $supplier->balance -= $purchase->due_amount;
        $supplier->save();


        DB::table('supplier_balance')->insert([
            'invoice_id' => $purchase->id,
            'balance_before' => $supplier->last_balance,
            'balance_after' => $supplier->balance,
            'user_id'=> auth()->user()->id
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
            'user_id'=> auth()->user()->id
        ]);

        return response()->json(['success' => true], 200);
    }
}
