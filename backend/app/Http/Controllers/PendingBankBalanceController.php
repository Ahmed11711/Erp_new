<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Order;
use App\Models\PendingBankBalance;
use App\Services\Accounting\AccountLinkingService;
use App\Services\Accounting\BankOperationalLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PendingBankBalanceController extends Controller
{
    public function pendingBanks(Request $request)
    {
        $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 10;
        $search = PendingBankBalance::query()->with(['user', 'bank']);
        if ($request->has('status')) {
            $search = $search->where('status', $request->status);
        }
        $search = $search->orderBy('id', 'desc')->paginate($itemsPerPage);

        return response()->json($search, 200);
    }

    public function pendingBanksStatus(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:pending_bank_balances,id',
            'status' => 'required|in:approved,rejected',
            'bank_id' => 'nullable|exists:banks,id',
            'counter_account_id' => 'nullable|exists:tree_accounts,id',
        ]);

        DB::beginTransaction();
        try {
            $pendingBank = PendingBankBalance::findOrFail($request->id);
            $pendingBank->status = $request->status;

            if ($request->status === 'approved') {
                if (! $request->bank_id) {
                    return response()->json(['message' => 'يجب اختيار البنك عند الموافقة'], 422);
                }

                $pendingBank->bank_id = $request->bank_id;
                $pendingBank->save();

                $bank = Bank::findOrFail($request->bank_id);
                $amount = (float) $pendingBank->amount;
                $counterAccountId = (int) ($request->counter_account_id ?: $this->resolveCounterAccountFromPending($pendingBank));

                if (! $counterAccountId) {
                    return response()->json([
                        'message' => 'يجب تحديد الحساب المقابل (counter_account_id) — لم يُستنتج من الطلب المرتبط',
                    ], 422);
                }

                $ledger = app(BankOperationalLedgerService::class);
                $ref = (string) ($pendingBank->ref ?? $pendingBank->id);
                $reason = $pendingBank->details ?? 'موافقة رصيد بنك معلّق';
                $absAmount = abs($amount);

                if ($amount >= 0) {
                    $ledger->deposit($bank, $absAmount, $counterAccountId, $reason, $pendingBank->type ?? 'موافقة', $ref);
                } else {
                    $ledger->withdraw($bank, $absAmount, $counterAccountId, $reason, $pendingBank->type ?? 'موافقة', $ref);
                }
            } else {
                $pendingBank->save();
            }

            DB::commit();

            return response()->json(['message' => 'success'], 201);
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function resolveCounterAccountFromPending(PendingBankBalance $pending): ?int
    {
        if (! is_numeric($pending->ref)) {
            return null;
        }

        $order = Order::find((int) $pending->ref);
        if (! $order) {
            return null;
        }

        $linking = app(AccountLinkingService::class);
        $account = $linking->resolveOrderCustomerAccount(
            (string) $order->customer_type,
            (string) ($order->customer_name ?? ''),
            $order->customer_phone ?? null,
            $order->company_id ? (int) $order->company_id : null,
            $order->order_source_id ? (int) $order->order_source_id : null
        );

        return $account?->id;
    }
}
