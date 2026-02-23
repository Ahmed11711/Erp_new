<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\SupplierPay;
use App\Models\Bank;
use App\Models\Safe;
use App\Models\ServiceAccount;
use App\Models\Supplier;
use App\Models\SupplierType;
use App\Models\TreeAccount;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\AccountEntry;
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

        // Auto-link to Tree Account
        // 1. Get Parent Account from Settings
        $settingKey = 'supplier_type_' . request('supplier_type') . '_parent_id';
        $parentAccountId = \App\Models\Setting::where('key', $settingKey)->value('value');
        
        // 2. If not in settings, try general supplier parent
        if (!$parentAccountId) {
             $parentAccountId = \App\Models\Setting::where('key', 'supplier_general_parent_id')->value('value');
        }

        // 3. Create Tree Account
        if ($parentAccountId) {
            $parentAccount = \App\Models\TreeAccount::find($parentAccountId);
             if ($parentAccount) {
                $lastChildCode = \App\Models\TreeAccount::where('parent_id', $parentAccount->id)->max('code');
                $newCode = $lastChildCode ? $lastChildCode + 1 : $parentAccount->code . '001'; // Simplified code gen

                $treeAccount = \App\Models\TreeAccount::create([
                    'name' => request('supplier_name'),
                    'parent_id' => $parentAccount->id,
                    'code' => $newCode,
                    'type' => $parentAccount->type,
                    'balance' => 0
                ]);
                
                $supplier->tree_account_id = $treeAccount->id;
                $supplier->save();
             }
        }

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

    public function supplierPay($id, Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'bank' => 'nullable|numeric|exists:banks,id',
            'payment_type' => 'nullable|in:safe,bank,service_account',
            'safe_id' => 'nullable|exists:safes,id',
            'bank_id' => 'nullable|exists:banks,id',
            'service_account_id' => 'nullable|exists:service_accounts,id',
        ]);

        $amount = (float) $request->amount;
        $paymentType = $request->payment_type ?? 'bank';
        $bankId = $request->bank_id ?? $request->bank;
        $safeId = $request->safe_id;
        $serviceAccountId = $request->service_account_id;
        if ($paymentType === 'bank' && !$bankId) {
            $bankId = $request->bank;
        }
        if (!$bankId && !$safeId && !$serviceAccountId) {
            return response()->json(['message' => 'يجب تحديد مصدر الدفع (خزينة أو بنك أو حساب خدمي)'], 422);
        }

        // Legacy: supplier_pays.bank_id is required; use first bank when paying from safe/service
        $payBankId = $bankId ?? Bank::query()->value('id');

        DB::beginTransaction();
        try {
            $pay = SupplierPay::create([
                'bank_id' => $payBankId,
                'safe_id' => $safeId,
                'service_account_id' => $serviceAccountId,
                'amount' => $amount,
                'supplier_id' => $id,
                'receipt_date' => date('Y-m-d'),
            ]);

            $supplier = Supplier::find($id);
            if (!$supplier) {
                DB::rollBack();
                return response()->json(['message' => 'المورد غير موجود'], 404);
            }
            $supplier->last_balance = $supplier->balance;
            $supplier->balance -= $amount;

            if (!$supplier->tree_account_id) {
                $parentAccount = TreeAccount::where('name', 'like', '%الموردين%')->first();
                if (!$parentAccount) {
                    $parentAccount = TreeAccount::firstOrCreate(
                        ['name' => 'الموردين'],
                        ['type' => 'liability', 'balance' => 0, 'code' => 2100]
                    );
                }
                $newAccount = TreeAccount::create([
                    'name' => $supplier->supplier_name ?? 'Supplier ' . $supplier->id,
                    'parent_id' => $parentAccount->id,
                    'code' => $parentAccount->code . $supplier->id,
                    'type' => 'liability',
                    'balance' => 0,
                    'debit_balance' => 0,
                    'credit_balance' => 0,
                    'level' => $parentAccount->level + 1,
                ]);
                $supplier->tree_account_id = $newAccount->id;
            }
            $supplier->save();

            DB::table('supplier_balance')->insert([
                'supplierpay_id' => $pay->id,
                'balance_before' => $supplier->last_balance,
                'balance_after' => $supplier->balance,
                'user_id' => auth()->user()->id,
            ]);

            $creditTreeId = null;
            $sourceName = '';

            if ($safeId) {
                $safe = Safe::find($safeId);
                if (!$safe || !$safe->account_id) {
                    DB::rollBack();
                    return response()->json(['message' => 'الخزينة غير مرتبطة بحساب في شجرة الحسابات'], 422);
                }
                $creditTreeId = $safe->account_id;
                $sourceName = $safe->name;
                $safe->decrement('balance', $amount);
            } elseif ($serviceAccountId) {
                $svc = ServiceAccount::find($serviceAccountId);
                if (!$svc || !$svc->account_id) {
                    DB::rollBack();
                    return response()->json(['message' => 'الحساب الخدمي غير مرتبط بحساب في شجرة الحسابات'], 422);
                }
                $creditTreeId = $svc->account_id;
                $sourceName = $svc->name;
                $svc->decrement('balance', $amount);
            } else {
                $bank = Bank::find($bankId);
                $balanceBefore = (float) $bank->balance;
                $bank->decrement('balance', $amount);
                DB::table('bank_details')->insert([
                    'bank_id' => $bankId,
                    'details' => ' سداد المورد ' . $supplier->supplier_name . ' بقيمة ' . $amount . 'ج',
                    'ref' => $pay->pay_number,
                    'type' => 'سداد',
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $bank->fresh()->balance,
                    'date' => date('Y-m-d'),
                    'created_at' => now(),
                    'user_id' => auth()->user()->id,
                ]);
                if ($bank->asset_id) {
                    $creditTreeId = $bank->asset_id;
                    $sourceName = $bank->name;
                }
            }

            $supplierTreeId = $supplier->tree_account_id;
            if ($supplierTreeId && $creditTreeId) {
                $lastEntry = DailyEntry::orderByDesc('entry_number')->first();
                $entryNumber = $lastEntry ? (int) $lastEntry->entry_number + 1 : 1;
                $dailyEntry = DailyEntry::create([
                    'date' => now(),
                    'entry_number' => str_pad($entryNumber, 6, '0', STR_PAD_LEFT),
                    'description' => 'سداد مورد - ' . $supplier->supplier_name . ' - ' . $sourceName,
                    'user_id' => auth()->id(),
                ]);
                DailyEntryItem::create([
                    'daily_entry_id' => $dailyEntry->id,
                    'account_id' => $supplierTreeId,
                    'debit' => $amount,
                    'credit' => 0,
                    'notes' => 'نقصان (سداد للمورد)',
                ]);
                DailyEntryItem::create([
                    'daily_entry_id' => $dailyEntry->id,
                    'account_id' => $creditTreeId,
                    'debit' => 0,
                    'credit' => $amount,
                    'notes' => 'نقصان (صرف من ' . $sourceName . ')',
                ]);
                AccountEntry::create([
                    'tree_account_id' => $supplierTreeId,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => 'سداد مورد - ' . $supplier->supplier_name,
                    'daily_entry_id' => $dailyEntry->id,
                ]);
                AccountEntry::create([
                    'tree_account_id' => $creditTreeId,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => 'سداد مورد - ' . $supplier->supplier_name,
                    'daily_entry_id' => $dailyEntry->id,
                ]);
                $supplierAcc = TreeAccount::find($supplierTreeId);
                $supplierAcc->increment('debit_balance', $amount);
                $supplierAcc->decrement('balance', $amount);
                $creditAcc = TreeAccount::find($creditTreeId);
                $creditAcc->increment('credit_balance', $amount);
                $creditAcc->decrement('balance', $amount);
            }

            DB::commit();
            return response()->json(['message' => 'success'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }


}
