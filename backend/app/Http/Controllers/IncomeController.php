<?php

namespace App\Http\Controllers;

use App\Models\Income;
use App\Models\Bank;
use App\Models\Safe;
use App\Models\ServiceAccount;
use App\Models\TreeAccount;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\AccountEntry;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class IncomeController extends Controller
{
    public function index()
    {
        $incomes = Income::query()
            ->with([
                'revenueTreeAccount:id,name,code,type',
                'bank:id,name',
                'safe:id,name',
                'serviceAccount:id,name',
            ])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        return response()->json($incomes);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|max:255',
            'date' => 'required|date',
            'income_amount' => 'required|numeric|min:0.01',
            'payment_type' => 'required|in:bank,safe,service_account',
            'bank_id' => 'required_if:payment_type,bank|nullable|exists:banks,id',
            'safe_id' => 'required_if:payment_type,safe|nullable|exists:safes,id',
            'service_account_id' => 'required_if:payment_type,service_account|nullable|exists:service_accounts,id',
            'revenue_tree_account_id' => 'required|exists:tree_accounts,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $revenueAcc = TreeAccount::query()
            ->where('id', $request->revenue_tree_account_id)
            ->whereIn('type', ['revenue', 'income'])
            ->whereDoesntHave('children')
            ->first();

        if (!$revenueAcc) {
            return response()->json([
                'message' => 'يجب اختيار حساب إيراد فرعي (ورقة) من شجرة الإيرادات.',
            ], 422);
        }

        $paymentType = $request->payment_type;
        $amount = (float) $request->income_amount;
        $debitTreeId = null;

        if ($paymentType === 'safe') {
            $safe = Safe::find($request->safe_id);
            if (!$safe || !$safe->account_id) {
                return response()->json(['message' => 'الخزينة غير مرتبطة بحساب في الشجرة المحاسبية.'], 422);
            }
            $debitTreeId = (int) $safe->account_id;
        } elseif ($paymentType === 'service_account') {
            $svc = ServiceAccount::find($request->service_account_id);
            if (!$svc || !$svc->account_id) {
                return response()->json(['message' => 'الحساب الخدمي غير مرتبط بشجرة الحسابات.'], 422);
            }
            $debitTreeId = (int) $svc->account_id;
        } else {
            $bank = Bank::find($request->bank_id);
            if (!$bank || !$bank->asset_id) {
                return response()->json(['message' => 'البنك غير مرتبط بحساب أصول في الشجرة المحاسبية.'], 422);
            }
            $debitTreeId = (int) $bank->asset_id;
        }

        DB::beginTransaction();
        try {
            $income = Income::create([
                'type' => $request->type,
                'date' => $request->date,
                'income_amount' => $amount,
                'revenue_tree_account_id' => $revenueAcc->id,
                'payment_type' => $paymentType,
                'bank_id' => $paymentType === 'bank' ? $request->bank_id : null,
                'safe_id' => $paymentType === 'safe' ? $request->safe_id : null,
                'service_account_id' => $paymentType === 'service_account' ? $request->service_account_id : null,
            ]);

            if ($paymentType === 'safe') {
                $safe = Safe::find($request->safe_id);
                $safe?->increment('balance', $amount);
            } elseif ($paymentType === 'service_account') {
                $svc = ServiceAccount::find($request->service_account_id);
                $svc?->increment('balance', $amount);
            } else {
                $bank = Bank::find($request->bank_id);
                if ($bank) {
                    $balanceBefore = (float) $bank->balance;
                    $bank->increment('balance', $amount);
                    DB::table('bank_details')->insert([
                        'bank_id' => $bank->id,
                        'details' => 'إيراد: ' . $request->type,
                        'ref' => $income->id,
                        'type' => 'إيرادات',
                        'amount' => $amount,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $bank->fresh()->balance,
                        'date' => $request->date,
                        'created_at' => now(),
                        'user_id' => auth()->id(),
                    ]);
                }
            }

            if ($debitTreeId && $revenueAcc) {
                $dailyEntry = DailyEntry::create([
                    'date' => $request->date,
                    'entry_number' => DailyEntry::getNextEntryNumber(),
                    'description' => 'إيراد: ' . $request->type,
                    'user_id' => auth()->id(),
                ]);

                DailyEntryItem::create([
                    'daily_entry_id' => $dailyEntry->id,
                    'account_id' => $debitTreeId,
                    'debit' => $amount,
                    'credit' => 0,
                    'notes' => 'تحصيل إيراد',
                ]);
                DailyEntryItem::create([
                    'daily_entry_id' => $dailyEntry->id,
                    'account_id' => $revenueAcc->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'notes' => 'إثبات إيراد: ' . $request->type,
                ]);

                AccountEntry::create([
                    'tree_account_id' => $debitTreeId,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => 'إيراد: ' . $request->type,
                    'daily_entry_id' => $dailyEntry->id,
                ]);
                AccountEntry::create([
                    'tree_account_id' => $revenueAcc->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => 'إيراد: ' . $request->type,
                    'daily_entry_id' => $dailyEntry->id,
                ]);

                $accService = app(AccountingService::class);
                $accService->updateAccountHierarchyBalances($debitTreeId);
                $accService->updateAccountHierarchyBalances($revenueAcc->id);
            } else {
                Log::warning('IncomeController: GL not posted — missing debit or revenue account', [
                    'income_id' => $income->id,
                    'debitTreeId' => $debitTreeId,
                    'revenueAcc' => $revenueAcc?->id,
                ]);
            }

            DB::commit();

            return response()->json($income, 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function show(Income $income)
    {
        return response()->json($income);
    }

    public function update(Request $request, Income $income)
    {
        return response()->json(['message' => 'غير مدعوم'], 501);
    }

    public function destroy(Income $income)
    {
        return response()->json(['message' => 'غير مدعوم'], 501);
    }
}
