<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\SupplierPay;
use App\Models\Bank;
use App\Models\Supplier;
use App\Models\SupplierType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator;
use Illuminate\Support\Facades\Cache;

class SupplierController extends Controller
{
    //


    public function index(){
        $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 5;
        $suppliers = Supplier::with('supplierType')->paginate($itemsPerPage);
        return response()->json($suppliers, 200);
    }
    public function supplier_names(){
        $data = Supplier::select('id', 'supplier_name')->get();
        return response()->json($data, 200);
    }
    public function store(Request $request){
        Validator::make($request->all(),[
            'supplier_name' => 'required|string',
            // 'supplier_phone' => 'required|string|unique:suppliers,supplier_phone|regex:/^01[0125][0-9]{8}$/',
            'supplier_address' => 'required|string',
            'supplier_type' => 'required|numeric|exists:supplier_types,id',
            'supplier_rate' => 'required|numeric|min:0|max:10',
            'price_rate' => 'required|numeric|min:0|max:10',
        ])->validate();

        $supplier = Supplier::create([
            'supplier_name' => request('supplier_name'),
            'supplier_phone' => request('supplier_phone'),
            'supplier_address' => request('supplier_address'),
            'supplier_type' => request('supplier_type'),
            'supplier_rate' => request('supplier_rate'),
            'price_rate' => request('price_rate'),
            'balance' => 0,
            'last_balance' => 0,
        ]);

        return response()->json(["success"=>true], 201);
    }



    public function search(Request $request){

        $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 5;
        $search = Supplier::query();
        if($request->has('supplier_name')){
            $search->where('supplier_name', 'like', '%'.$request->supplier_name.'%');
        }
        if($request->has('supplier_type')){
            $search->where('supplier_type', $request->supplier_type);
        }
        if($request->has('supplier_phone')){
            $search->where('supplier_phone', 'like', '%'.$request->supplier_phone.'%');
        }
        if ($request->has('status')) {
            if ($request->status == 'own') {
                $search->where('balance', '<', 0);
            } elseif($request->status == 'want'){
                $search->where('balance', '>', 0);
            }
        }
        $search->with('supplierType');
        $suppliers = $search->paginate($itemsPerPage);

        $sumOfBalance = $search->sum('balance');
        $response = [
            'suppliers' => $suppliers,
            'sum_of_balance' => $sumOfBalance,
        ];

        return response()->json($response, 200);
    }


    public function supplier_details(Request $request, $id)
{
    $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 15;

    $supplierId = $id;
    $name = Supplier::where('id', $id)->value('supplier_name');

    // $invoicesWithBalances = DB::table('purchases as p')
    //     ->select(
    //         'p.id as invoice_id',
    //         'sb.balance_before as balance_before',
    //         'sb.balance_after as balance_after',
    //         'u.name as user_name',
    //         'p.invoice_number as invoice_number',
    //         'p.receipt_date as receipt_date',
    //         'p.total_price as total_price',
    //         'p.paid_amount as paid_amount',
    //         'p.due_amount as due_amount',
    //         'p.invoice_type'
    //     )
    //     ->leftJoin('supplier_balance as sb', 'p.id', '=', 'sb.invoice_id')
    //     ->leftJoin('users as u', 'sb.user_id', '=', 'u.id')
    //     ->where('p.supplier_id', $supplierId)
    //     ->paginate($itemsPerPage);



    $invoicesAndPays = DB::table('purchases as p')
        ->select(
            'p.id as invoice_id',
            'sb.balance_before as balance_before',
            'sb.balance_after as balance_after',
            'u.name as user_name',
            'p.invoice_number as invoice_number',
            'p.receipt_date as receipt_date',
            'p.total_price as total_price',
            'p.paid_amount as paid_amount',
            'p.due_amount as due_amount',
            'p.invoice_type',
            'p.created_at as created_at'
        )
        ->leftJoin('supplier_balance as sb', 'p.id', '=', 'sb.invoice_id')
        ->leftJoin('users as u', 'sb.user_id', '=', 'u.id')
        ->where('p.supplier_id', $supplierId);

    $payWithBalances = DB::table('supplier_pays as sp')
        ->select(
            'sp.id as invoice_id',
            'sb.balance_before as balance_before',
            'sb.balance_after as balance_after',
            'u.name as user_name',
            'sp.pay_number as invoice_number',
            'sp.receipt_date as receipt_date',
            'sp.amount as total_price',
            'sp.amount as paid_amount',
            'sp.amount as due_amount',
            'sp.pay_number',
            'sp.created_at as created_at'
        )
        ->leftJoin('supplier_balance as sb', 'sp.id', '=', 'sb.supplierpay_id') // adjust the foreign key
        ->leftJoin('users as u', 'sb.user_id', '=', 'u.id')
        ->where('sp.supplier_id', $supplierId);

    // Combine the queries with union
    $invoicesAndPays = $invoicesAndPays->union($payWithBalances)->orderBy('created_at','desc')->paginate($itemsPerPage);



    $result = [
        'data' => $invoicesAndPays,
        'name'=>$name,
    ];

    return response()->json($result, 200);
}











    public function getAllSupplierTypes(){
        $supplierTypes = SupplierType::all();
        return response()->json($supplierTypes, 200);
    }
    public function StoreSupplierType(Request $request){
        validator::make($request->all(),[
            'supplier_type' => 'required|string',
        ])->validate();
        $supplierType = SupplierType::create([
            'supplier_type' => request('supplier_type'),
        ]);
        return response()->json($supplierType, 201);
    }

    public function deleteType($id){
        $supplierType = SupplierType::find($id);
        if(!$supplierType){
            return response()->json(['error' => 'Not Found'], 404);
        }
        $supplierType->delete();
        return response()->json(['message' => 'Deleted Successfully'], 200);
    }

    public function supplierPay($id , Request $request){

        $amount = request('amount');
        $bankId = request('bank');

        $request->validate(
            [
                'bank' => 'required|numeric|exists:banks,id',
                'amount' => 'required|numeric|min:0',
            ]);

        DB::beginTransaction();
        try {
            $pay = SupplierPay::create([
                'bank_id' => request('bank'),
                'amount' => request('amount'),
                'supplier_id' => $id,
                'receipt_date' => date('Y-m-d')
            ]);
    
            $supplier = Supplier::find($id);
            $supplier->last_balance = $supplier->balance;
            $supplier->balance -= $amount;
            
            // ---------------------------------------------------------
            // 1. Ensure Supplier Tree Account Exists
            // ---------------------------------------------------------
            if (!$supplier->tree_account_id) {
                // Auto-create Account
                $parentAccount = \App\Models\TreeAccount::where('name', 'like', '%الموردين%')->first(); 
                if (!$parentAccount) {
                    $parentAccount = \App\Models\TreeAccount::firstOrCreate(
                        ['name' => 'الموردين'],
                        ['type' => 'liability', 'balance' => 0, 'code' => '2100'] 
                    );
                }

                $newAccount = \App\Models\TreeAccount::create([
                    'name' => $supplier->supplier_name ?? 'Supplier ' . $supplier->id,
                    'parent_id' => $parentAccount->id,
                    'code' => $parentAccount->code . $supplier->id,
                    'type' => 'liability',
                    'balance' => 0,
                    'debit_balance' => 0,
                    'credit_balance' => 0,
                ]);
                
                $supplier->tree_account_id = $newAccount->id;
            }
            $supplier->save(); // Save balance and tree_id
            
            // ---------------------------------------------------------
            // 2. Operational Updates (Legacy)
            // ---------------------------------------------------------
            DB::table('supplier_balance')->insert([
                'supplierpay_id' => $pay->id,
                'balance_before' => $supplier->last_balance,
                'balance_after' => $supplier->balance,
                'user_id'=> auth()->user()->id
            ]);
    
            $bank = Bank::find($bankId);
            $paid = (double)$amount;
            $balance =(double) $bank->balance;
            $bank->balance= $balance- $paid;
            $bank->save();
    
            DB::table('bank_details')->insert([
                'bank_id' => $bankId,
                'details' => ' سداد المورد '.$supplier->supplier_name.' بقيمة '.$amount.'ج',
                'ref' => $pay->pay_number,
                'type' => "سداد",
                'amount' => (double)$amount,
                'balance_before' => $balance,
                'balance_after' => $bank->balance,
                'date' => date('Y-m-d'),
                'created_at' => now(),
                'user_id'=> auth()->user()->id
            ]);

            // ---------------------------------------------------------
            // 3. Accounting Entries
            // ---------------------------------------------------------
            // Debit: Supplier (Liability decrease)
            // Credit: Bank (Asset decrease)
            
            $supplierTreeId = $supplier->tree_account_id;
            $bankTreeId = $bank->asset_id;

            if ($bankTreeId) {
                // Create Debit Entry (Supplier)
                \App\Models\AccountEntry::create([
                    'tree_account_id' => $supplierTreeId,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => "سداد مورد - " . $supplier->supplier_name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $supplierAcc = \App\Models\TreeAccount::find($supplierTreeId);
                $supplierAcc->increment('debit_balance', $amount);
                // Liability: Balance = Credit - Debit
                $supplierAcc->decrement('balance', $amount); 
                
                // Create Credit Entry (Bank)
                \App\Models\AccountEntry::create([
                    'tree_account_id' => $bankTreeId,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => "سداد مورد - " . $supplier->supplier_name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $bankAcc = \App\Models\TreeAccount::find($bankTreeId);
                $bankAcc->increment('credit_balance', $amount);
                // Asset: Balance = Debit - Credit
                $bankAcc->decrement('balance', $amount);
            }

            DB::commit();
            return response()->json(['message' => 'success'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }


}
