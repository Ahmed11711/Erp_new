<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Safe;
use App\Models\ServiceAccount;
use App\Models\TreeAccount;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\AccountEntry;
use Illuminate\Http\Request;
use App\Models\customerCompany;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\V2\TreeAccount\AddAssetController;


class CustomerCompanyController extends Controller
{
    
    public function __construct(public AddAssetController $addAsset)
    {
    }
    public function index()
    {
        $companies = customerCompany::all();
        return response()->json($companies);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:customer_companies,name',
            'phone1' => 'required|unique:customer_companies,phone1',
            'governorate' => 'required',
            'address' => 'required',
        ]);

 
        customerCompany::create([
            'name' => $request->name,
            'phone1' => $request->phone1,
            'phone2' => $request->phone2,
            'phone3' => $request->phone3,
            'phone4' => $request->phone4,
            'tel' => $request->tel,
            'governorate' => $request->governorate,
            'city' => $request->city,
            'address' => $request->address,
        ]);
        // Fetch parent account from settings
        $parentAccountId = \App\Models\Setting::where('key', 'customer_corporate_parent_account_id')->value('value');
        
        $this->addAsset->Addcustomer($request->name,'شركة', $parentAccountId);
        return response()->json(['message'=>'success'], 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\customerCompany  $customerCompany
     * @return \Illuminate\Http\Response
     */
    public function show(customerCompany $customerCompany)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\customerCompany  $customerCompany
     * @return \Illuminate\Http\Response
     */
    public function edit(customerCompany $customerCompany)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\customerCompany  $customerCompany
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, customerCompany $customerCompany)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\customerCompany  $customerCompany
     * @return \Illuminate\Http\Response
     */
    public function destroy(customerCompany $customerCompany)
    {
        //
    }

    public function search(Request $request){

        $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 10;
        $search = customerCompany::query();
        if($request->has('name')){
            $search->where('name', 'like' ,  '%'.$request->name.'%');
        }
        if($request->has('phone')){
            $search->where('phone1','like' , $request->phone.'%');
        }

        $search = $search->orderBy('id' , 'desc')->paginate($itemsPerPage);
        return response()->json($search, 200);
    }


    public function customerCompanyBalance($id , Request $request){
        $itemsPerPage = $request->input('itemsPerPage', 15);

        $name = customerCompany::where('id', $id)->value('name');

        $data = DB::table('customer_company_details')
        ->join('users', 'customer_company_details.user_id', '=', 'users.id')
        ->select('customer_company_details.*', 'users.name')
        ->where('customer_company_id', $id)
        // ->orderBy('customer_company_details.created_at', 'desc')
        ->orderBy('customer_company_details.id', 'desc')
        ->paginate($itemsPerPage);


        $result = [
            'data' => $data,
            $name,
        ];

        return response()->json($result, 200);
    }

    public function companyCollect($id, Request $request)
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
        $sourceId = $request->safe_id ?? $request->bank_id ?? $request->service_account_id ?? $request->bank;
        if ($paymentType === 'bank' && !$sourceId && $request->bank) {
            $sourceId = $request->bank;
        }
        if (!$sourceId) {
            return response()->json(['message' => 'يجب تحديد مصدر التحصيل (خزينة أو بنك أو حساب خدمي)'], 422);
        }

        $company = customerCompany::find($id);
        if (!$company) {
            return response()->json(['message' => 'الشركة غير موجودة'], 404);
        }

        // Resolve debit tree account (where money is received: safe/bank/service)
        $debitTreeId = null;
        $sourceName = '';
        $sourceBalanceBefore = 0;
        if ($paymentType === 'safe') {
            $safe = Safe::find($sourceId);
            if (!$safe || !$safe->account_id) {
                return response()->json(['message' => 'الخزينة غير مرتبطة بحساب في شجرة الحسابات'], 422);
            }
            $debitTreeId = $safe->account_id;
            $sourceName = $safe->name;
            $sourceBalanceBefore = (float) $safe->balance;
        } elseif ($paymentType === 'service_account') {
            $svc = ServiceAccount::find($sourceId);
            if (!$svc || !$svc->account_id) {
                return response()->json(['message' => 'الحساب الخدمي غير مرتبط بحساب في شجرة الحسابات'], 422);
            }
            $debitTreeId = $svc->account_id;
            $sourceName = $svc->name;
            $sourceBalanceBefore = (float) $svc->balance;
        } else {
            $bank = Bank::find($sourceId);
            if (!$bank) {
                return response()->json(['message' => 'البنك غير موجود'], 404);
            }
            $sourceBalanceBefore = (float) $bank->balance;
            if ($bank->asset_id) {
                $debitTreeId = $bank->asset_id;
                $sourceName = $bank->name;
            }
        }

        // Ensure company has tree account (for accounting entry)
        if (!$company->tree_account_id) {
            $parentAccountId = \App\Models\Setting::where('key', 'customer_corporate_parent_account_id')->value('value');
            $parentAccount = $parentAccountId ? TreeAccount::find($parentAccountId) : null;
            if (!$parentAccount) {
                $parentAccount = TreeAccount::where('name', 'like', '%العملاء%')->first();
                if (!$parentAccount) {
                    $parentAccount = TreeAccount::firstOrCreate(
                        ['name' => 'العملاء'],
                        ['type' => 'asset', 'balance' => 0, 'code' => 1100, 'level' => 1]
                    );
                }
            }
            $newAccount = TreeAccount::create([
                'name' => $company->name,
                'parent_id' => $parentAccount->id,
                'code' => $parentAccount->code . $company->id,
                'type' => 'asset',
                'balance' => 0,
                'debit_balance' => 0,
                'credit_balance' => 0,
                'level' => $parentAccount->level + 1,
            ]);
            $company->tree_account_id = $newAccount->id;
        }
        $customerTreeId = $company->tree_account_id;

        $companyBalanceBefore = (float) $company->balance;
        $company->balance = $company->balance - $amount;
        $company->save();

        $lastIndex = DB::table('customer_company_details')->orderBy('id', 'desc')->first();
        $ref = 'C' . (($lastIndex->id ?? 0) + 1);

        DB::table('customer_company_details')->insert([
            'bank_id' => $paymentType === 'bank' ? $sourceId : null,
            'customer_company_id' => $company->id,
            'ref' => $ref,
            'details' => ' تحصيل من حساب الشركة ',
            'type' => 'تحصيل',
            'amount' => $amount,
            'balance_before' => $companyBalanceBefore,
            'balance_after' => $company->balance,
            'date' => date('Y-m-d'),
            'created_at' => now(),
            'user_id' => auth()->user()->id,
        ]);

        // Update source balance
        if ($paymentType === 'safe') {
            Safe::where('id', $sourceId)->increment('balance', $amount);
        } elseif ($paymentType === 'service_account') {
            ServiceAccount::where('id', $sourceId)->increment('balance', $amount);
        } else {
            Bank::where('id', $sourceId)->increment('balance', $amount);
            DB::table('bank_details')->insert([
                'bank_id' => $sourceId,
                'details' => ' تحصيل من حساب شركة ' . $company->name,
                'ref' => $ref,
                'type' => 'تحصيل عملاء شركات',
                'amount' => $amount,
                'balance_before' => $sourceBalanceBefore,
                'balance_after' => $sourceBalanceBefore + $amount,
                'date' => date('Y-m-d'),
                'created_at' => now(),
                'user_id' => auth()->user()->id,
            ]);
        }

        // Accounting: Daily Entry + AccountEntry (debit source, credit customer) — appears in daily register
        if ($debitTreeId && $customerTreeId) {
            $lastEntry = DailyEntry::orderByDesc('entry_number')->first();
            $entryNumber = $lastEntry ? (int) $lastEntry->entry_number + 1 : 1;
            $dailyEntry = DailyEntry::create([
                'date' => now(),
                'entry_number' => str_pad($entryNumber, 6, '0', STR_PAD_LEFT),
                'description' => 'تحصيل من شركة - ' . $company->name . ' - ' . $sourceName,
                'user_id' => auth()->id(),
            ]);
            DailyEntryItem::create([
                'daily_entry_id' => $dailyEntry->id,
                'account_id' => $debitTreeId,
                'debit' => $amount,
                'credit' => 0,
                'notes' => 'زيادة (تحصيل) في ' . $sourceName,
            ]);
            DailyEntryItem::create([
                'daily_entry_id' => $dailyEntry->id,
                'account_id' => $customerTreeId,
                'debit' => 0,
                'credit' => $amount,
                'notes' => 'نقصان (تحصيل من العميل)',
            ]);
            AccountEntry::create([
                'tree_account_id' => $debitTreeId,
                'debit' => $amount,
                'credit' => 0,
                'description' => 'تحصيل من شركة - ' . $company->name,
                'daily_entry_id' => $dailyEntry->id,
            ]);
            AccountEntry::create([
                'tree_account_id' => $customerTreeId,
                'debit' => 0,
                'credit' => $amount,
                'description' => 'تحصيل من شركة - ' . $company->name,
                'daily_entry_id' => $dailyEntry->id,
            ]);
            $debitAcc = TreeAccount::find($debitTreeId);
            $creditAcc = TreeAccount::find($customerTreeId);
            $debitAcc->increment('balance', $amount);
            $debitAcc->increment('debit_balance', $amount);
            $creditAcc->decrement('balance', $amount);
            $creditAcc->increment('credit_balance', $amount);
        }

        return response()->json(['message' => 'success'], 200);
    }
}
