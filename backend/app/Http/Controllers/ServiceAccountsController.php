<?php

namespace App\Http\Controllers;

use App\Models\ServiceAccount;
use App\Models\TreeAccount;
use App\Models\AccountEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ServiceAccountsController extends Controller
{
    public function index()
    {
        $accounts = ServiceAccount::with('account')->get();
        return response()->json($accounts);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'other_info' => 'nullable|string',
            'img' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'account_id' => 'required|exists:tree_accounts,id',
            'balance' => 'numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->except('img');

        if ($request->hasFile('img')) {
            $imageName = time() . '.' . $request->img->extension();
            $request->img->move(public_path('images/service_accounts'), $imageName);
            $data['img'] = 'images/service_accounts/' . $imageName;
        }

        $account = ServiceAccount::create($data);
        return response()->json($account, 201);
    }

    public function update(Request $request, $id)
    {
        $account = ServiceAccount::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'account_number' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'other_info' => 'nullable|string',
            'img' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'account_id' => 'exists:tree_accounts,id',
            'balance' => 'numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->except('img');

        if ($request->hasFile('img')) {
            // Delete old image if needed
            if ($account->img && file_exists(public_path($account->img))) {
                unlink(public_path($account->img));
            }
            $imageName = time() . '.' . $request->img->extension();
            $request->img->move(public_path('images/service_accounts'), $imageName);
            $data['img'] = 'images/service_accounts/' . $imageName;
        }

        $account->update($data);
        return response()->json($account);
    }


    public function transfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_account_id' => 'required|exists:service_accounts,id',
            'to_account_id' => 'required|exists:service_accounts,id|different:from_account_id',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $fromAccount = ServiceAccount::find($request->from_account_id);
            $toAccount = ServiceAccount::find($request->to_account_id);

            if ($fromAccount->balance < $request->amount) {
                return response()->json(['message' => 'رصيد الحساب المصدر غير كافي'], 422);
            }

            // Update balances
            $fromAccount->decrement('balance', $request->amount);
            $toAccount->increment('balance', $request->amount);

            // Create accounting entries if accounts are set
            // From Account (Credit)
            if ($fromAccount->account_id) {
                AccountEntry::create([
                    'tree_account_id' => $fromAccount->account_id,
                    'debit' => 0,
                    'credit' => $request->amount,
                    'description' => "تحويل من حساب خدمي {$fromAccount->name} إلى حساب خدمي {$toAccount->name} - " . ($request->notes ?? ''),
                ]);
                $fromTree = TreeAccount::find($fromAccount->account_id);
                if ($fromTree) {
                    $fromTree->increment('credit_balance', $request->amount);
                    if (in_array($fromTree->type, ['asset', 'expense'])) {
                        $fromTree->decrement('balance', $request->amount);
                    } else {
                        $fromTree->increment('balance', $request->amount);
                    }
                }
            }

            // To Account (Debit)
            if ($toAccount->account_id) {
                AccountEntry::create([
                    'tree_account_id' => $toAccount->account_id,
                    'debit' => $request->amount,
                    'credit' => 0,
                    'description' => "تحويل من حساب خدمي {$fromAccount->name} إلى حساب خدمي {$toAccount->name} - " . ($request->notes ?? ''),
                ]);
                $toTree = TreeAccount::find($toAccount->account_id);
                if ($toTree) {
                    $toTree->increment('debit_balance', $request->amount);
                    if (in_array($toTree->type, ['asset', 'expense'])) {
                        $toTree->increment('balance', $request->amount);
                    } else {
                        $toTree->decrement('balance', $request->amount);
                    }
                }
            }

            DB::commit();
            return response()->json(['message' => 'تم التحويل بنجاح'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }
}
