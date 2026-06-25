<?php

namespace App\Services\Accounting;

use App\Models\Bank;
use App\Models\customerCompany;
use App\Models\TreeAccount;
use Illuminate\Support\Facades\DB;

/**
 * يربط إيداع/سحب البنك أو الخزينة (على حساب شجري لعميل شركة)
 * بدفتر customer_company_details ورصيد customer_companies.
 */
class CustomerCompanyDirectCashSyncService
{
    public function syncIfCustomerCompanyTreeAccount(
        TreeAccount $counterAccount,
        string $storageType,
        float $amount,
        ?int $bankId,
        string $ref,
        string $details,
        string $date,
        ?int $userId = null
    ): bool {
        $company = customerCompany::where('tree_account_id', $counterAccount->id)->first();
        if (! $company) {
            return false;
        }

        if (DB::table('customer_company_details')->where('ref', $ref)->exists()) {
            return true;
        }

        $userId = $userId ?? auth()->id();
        if (! $userId) {
            return false;
        }

        $balanceBefore = (float) $company->balance;

        if ($storageType === 'deposit') {
            $company->balance = $balanceBefore - $amount;
            $type = 'تحصيل';
        } else {
            $company->balance = $balanceBefore + $amount;
            $type = 'صرف';
        }
        $company->save();

        DB::table('customer_company_details')->insert([
            'bank_id' => $bankId,
            'customer_company_id' => $company->id,
            'ref' => $ref,
            'details' => $details,
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => (float) $company->balance,
            'date' => $date,
            'created_at' => now(),
            'user_id' => $userId,
        ]);

        return true;
    }

    public function reverseByRef(string $ref): void
    {
        $detail = DB::table('customer_company_details')->where('ref', $ref)->first();
        if (! $detail) {
            return;
        }

        $company = customerCompany::find($detail->customer_company_id);
        if ($company) {
            $delta = (float) $detail->balance_after - (float) $detail->balance_before;
            $company->balance = (float) $company->balance - $delta;
            $company->save();
        }

        DB::table('customer_company_details')->where('id', $detail->id)->delete();
    }

    public function buildCashLabel(string $channel, string $storageType, string $batchCode, string $notes = ''): string
    {
        $label = match (true) {
            $channel === 'bank' && $storageType === 'deposit' => 'إيداع بنكي',
            $channel === 'bank' && $storageType === 'withdrawal' => 'سحب بنكي',
            $channel === 'safe' && $storageType === 'deposit' => 'إيداع خزينة',
            default => 'صرف خزينة',
        };

        $desc = "{$label} [{$batchCode}]";
        if ($notes !== '') {
            $desc .= ' - ' . $notes;
        }

        return $desc;
    }

    public function syncBankTransaction(
        Bank $bank,
        TreeAccount $counterAccount,
        string $storageType,
        float $amount,
        string $date,
        string $notes,
        string $batchCode
    ): void {
        $details = $this->buildCashLabel('bank', $storageType, $batchCode, $notes);
        $bankId = $storageType === 'deposit' ? $bank->id : null;

        $this->syncIfCustomerCompanyTreeAccount(
            $counterAccount,
            $storageType,
            $amount,
            $bankId,
            $batchCode,
            $details,
            $date
        );
    }
}
