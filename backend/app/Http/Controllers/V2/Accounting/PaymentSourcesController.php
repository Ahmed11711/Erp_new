<?php

namespace App\Http\Controllers\V2\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Safe;
use App\Models\Bank;
use App\Models\ServiceAccount;
use Illuminate\Http\Request;

/**
 * Unified payment sources: treasury (safes), banks, service accounts.
 * One place to choose source for payment and see follow-up (balance, linked account).
 */
class PaymentSourcesController extends Controller
{
    /**
     * List all payment sources with linked tree account and balance for follow-up.
     * GET accounting/payment-sources
     */
    public function index(Request $request)
    {
        $safes = Safe::with('account:id,name,code,balance,debit_balance,credit_balance')
            ->orderBy('name')
            ->get()
            ->map(function ($safe) {
                return [
                    'type' => 'safe',
                    'id' => $safe->id,
                    'name' => $safe->name,
                    'balance' => (float) $safe->balance,
                    'account_id' => $safe->account_id,
                    'account' => $safe->account ? [
                        'id' => $safe->account->id,
                        'name' => $safe->account->name,
                        'code' => $safe->account->code,
                        'balance' => (float) $safe->account->balance,
                        'debit_balance' => (float) $safe->account->debit_balance,
                        'credit_balance' => (float) $safe->account->credit_balance,
                    ] : null,
                    'label' => 'خزينة',
                ];
            });

        $banks = Bank::with('asset:id,name,code,balance,debit_balance,credit_balance')
            ->orderBy('name')
            ->get()
            ->map(function ($bank) {
                $account = $bank->asset;
                return [
                    'type' => 'bank',
                    'id' => $bank->id,
                    'name' => $bank->name,
                    'balance' => (float) $bank->balance,
                    'account_id' => $bank->asset_id,
                    'account' => $account ? [
                        'id' => $account->id,
                        'name' => $account->name,
                        'code' => $account->code,
                        'balance' => (float) $account->balance,
                        'debit_balance' => (float) $account->debit_balance,
                        'credit_balance' => (float) $account->credit_balance,
                    ] : null,
                    'label' => 'بنك',
                ];
            });

        $serviceAccounts = ServiceAccount::with('account:id,name,code,balance,debit_balance,credit_balance')
            ->orderBy('name')
            ->get()
            ->map(function ($svc) {
                return [
                    'type' => 'service_account',
                    'id' => $svc->id,
                    'name' => $svc->name,
                    'balance' => (float) $svc->balance,
                    'account_id' => $svc->account_id,
                    'account' => $svc->account ? [
                        'id' => $svc->account->id,
                        'name' => $svc->account->name,
                        'code' => $svc->account->code,
                        'balance' => (float) $svc->account->balance,
                        'debit_balance' => (float) $svc->account->debit_balance,
                        'credit_balance' => (float) $svc->account->credit_balance,
                    ] : null,
                    'label' => 'حساب خدمي',
                ];
            });

        return response()->json([
            'safes' => $safes,
            'banks' => $banks,
            'service_accounts' => $serviceAccounts,
        ], 200);
    }
}
