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

class IncomeController extends Controller
{
    public function index()
    {
        $incomes = Income::all();
        return response()->json($incomes);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required',
            'date' => 'required|date',
            'income_amount' => 'required|numeric|min:0.01',
            'payment_type' => 'nullable|in:bank,safe,service_account',
            'bank_id' => 'nullable|exists:banks,id',
            'safe_id' => 'nullable|exists:safes,id',
            'service_account_id' => 'nullable|exists:service_accounts,id',
        ]);

        DB::beginTransaction();
        try {
            $income = Income::create($request->all());
            $amount = (float) $request->income_amount;

            $debitTreeId = null;
            $paymentType = $request->payment_type ?? 'bank';

            if ($paymentType === 'safe' && $request->safe_id) {
                $safe = Safe::find($request->safe_id);
                if ($safe) {
                    $safe->increment('balance', $amount);
                    $debitTreeId = $safe->account_id;
                }
            } elseif ($paymentType === 'service_account' && $request->service_account_id) {
                $svc = ServiceAccount::find($request->service_account_id);
                if ($svc) {
                    $svc->increment('balance', $amount);
                    $debitTreeId = $svc->account_id;
                }
            } elseif ($request->bank_id) {
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
                    $debitTreeId = $bank->asset_id;
                }
            }

            $revenueAcc = TreeAccount::where('type', 'revenue')
                ->where(function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->type . '%')
                      ->orWhere('name', 'like', '%إيرادات أخرى%')
                      ->orWhere('name_en', 'like', '%other income%');
                })
                ->whereDoesntHave('children')
                ->first();

            if (!$revenueAcc) {
                $revenueAcc = TreeAccount::where('type', 'revenue')
                    ->whereDoesntHave('children')
                    ->orderBy('code')
                    ->first();
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
}
