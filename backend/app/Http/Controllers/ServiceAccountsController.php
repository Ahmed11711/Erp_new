<?php

namespace App\Http\Controllers;

use App\Models\ServiceAccount;
use App\Models\TreeAccount;
use App\Models\AccountEntry;
use App\Services\Accounting\AccountingService;
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
        $balance = (float) $request->input('balance', 0);

        $rules = [
            'name' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'other_info' => 'nullable|string',
            'img' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'account_id' => 'required|exists:tree_accounts,id',
            'balance' => 'nullable|numeric|min:0',
            'counter_account_id' => 'nullable|exists:tree_accounts,id',
        ];

        if ($balance > 0.000001) {
            $rules['counter_account_id'] = 'required|exists:tree_accounts,id';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->except(['img', 'counter_account_id']);

        if ($request->hasFile('img')) {
            $imageName = time() . '.' . $request->img->extension();
            $request->img->move(public_path('images/service_accounts'), $imageName);
            $data['img'] = 'images/service_accounts/' . $imageName;
        }

        DB::beginTransaction();
        try {
            $serviceAccount = ServiceAccount::create($data);

            if ($balance > 0.000001) {
                $serviceAccountId = (int) $request->account_id;
                $counterId = (int) $request->counter_account_id;

                if ($serviceAccountId === $counterId) {
                    throw new \InvalidArgumentException('الحساب المقابل يجب أن يكون مختلفاً عن الحساب المرتبط');
                }

                $now = now();
                $desc = 'رصيد افتتاحي حساب خدمي - ' . $serviceAccount->name;

                AccountEntry::create([
                    'tree_account_id' => $serviceAccountId,
                    'debit' => $balance,
                    'credit' => 0,
                    'description' => $desc,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                AccountEntry::create([
                    'tree_account_id' => $counterId,
                    'debit' => 0,
                    'credit' => $balance,
                    'description' => $desc,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                app(AccountingService::class)->updateAccountHierarchyBalances($serviceAccountId);
                app(AccountingService::class)->updateAccountHierarchyBalances($counterId);
            }

            DB::commit();

            return response()->json($serviceAccount->load('account'), 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 500);
        }
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
            'account_id' => 'required|exists:tree_accounts,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->except(['img', 'balance', 'counter_account_id']);

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

    public function destroy($id)
    {
        $account = ServiceAccount::find($id);
        if (!$account) {
            return response()->json(['message' => 'الحساب الخدمي غير موجود'], 404);
        }

        if (abs((float) $account->balance) > 0.000001) {
            return response()->json(['message' => 'لا يمكن حذف حساب خدمي له رصيد. صفِّر الرصيد أولاً عبر تحويل أو عملية مالية.'], 422);
        }

        DB::beginTransaction();
        try {
            if ($account->img && file_exists(public_path($account->img))) {
                unlink(public_path($account->img));
            }

            $account->delete();

            DB::commit();

            return response()->json(['message' => 'تم حذف الحساب الخدمي بنجاح'], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function transfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_account_id' => 'required|exists:service_accounts,id',
            'to_account_id' => 'required|exists:service_accounts,id|different:from_account_id',
            'amount' => 'required|numeric|min:0.01',
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
                    $fromTree->save();
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
                    $toTree->save();
                }
            }

            DB::commit();
            return response()->json(['message' => 'تم التحويل بنجاح'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Handle Direct Deposit/Withdraw (Receipt/Payment)
     */
    public function directTransaction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'service_account_id' => 'required|exists:service_accounts,id',
            'type' => 'required|in:receipt,payment',
            'counter_account_id' => 'required|exists:tree_accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $serviceAccount = ServiceAccount::find($request->service_account_id);
            $counterAccount = TreeAccount::find($request->counter_account_id);
            $amount = $request->amount;
            $type = $request->type;
            $date = $request->date;
            $notes = $request->notes;

            // Ensure Service Account has a linked Tree Account
            if (!$serviceAccount->account_id) {
                return response()->json(['message' => 'الحساب الخدمي غير مرتبط بحساب شجري'], 422);
            }
            $serviceAccountId = $serviceAccount->account_id;

            // Check Balance for Withdrawal (Payment)
            if ($type === 'payment' && $serviceAccount->balance < $amount) {
                return response()->json(['message' => 'رصيد الحساب الخدمي غير كافي'], 422);
            }

            // Note: ServiceAccounts don't have a transaction history table yet. 
            // We rely on AccountEntry for history.

            if ($type === 'receipt') {
                AccountEntry::create([
                    'tree_account_id' => $serviceAccountId,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => "إيداع حساب خدمي - " . $notes,
                    'created_at' => $date,
                    'updated_at' => $date
                ]);
                AccountEntry::create([
                    'tree_account_id' => $counterAccount->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => "إيداع حساب خدمي - " . $notes,
                    'created_at' => $date,
                    'updated_at' => $date
                ]);
                $serviceAccount->increment('balance', $amount);
            } else {
                AccountEntry::create([
                    'tree_account_id' => $serviceAccountId,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => "صرف حساب خدمي - " . $notes,
                    'created_at' => $date,
                    'updated_at' => $date
                ]);
                AccountEntry::create([
                    'tree_account_id' => $counterAccount->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => "صرف حساب خدمي - " . $notes,
                    'created_at' => $date,
                    'updated_at' => $date
                ]);
                $serviceAccount->decrement('balance', $amount);
            }

            $accService = app(\App\Services\Accounting\AccountingService::class);
            $accService->updateAccountHierarchyBalances($serviceAccountId);
            $accService->updateAccountHierarchyBalances($counterAccount->id);

            DB::commit();
            return response()->json(['message' => 'تمت العملية بنجاح'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }
}
