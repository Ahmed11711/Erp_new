<?php

namespace App\Services\Accounting;

use App\Models\AccountEntry;
use App\Models\Offers;
use App\Models\TreeAccount;
use App\Models\customerCompany;
use Illuminate\Support\Facades\Log;

/**
 * ترحيل مديونية عرض السعر على حساب الشجرة:
 * مدين ذمة عميل الشركة / دائن إيرادات المبيعات.
 */
class OfferDebtAccountingService
{
    public function __construct(
        private AccountLinkingService $accountLinking,
        private LedgerJournalService $journal,
    ) {}

    public function batchPrefix(int $offerId): string
    {
        return 'OFFER-' . $offerId . '-';
    }

    public function hasGlPosted(int $offerId): bool
    {
        return AccountEntry::query()
            ->where('entry_batch_code', 'like', $this->batchPrefix($offerId) . '%')
            ->exists();
    }

    /**
     * @return array{batch_code: string, customer_account_id: int, sales_account_id: int, amount: float}|null
     */
    public function postOfferDebt(Offers $offer, customerCompany $company, float $amount, ?int $userId = null): ?array
    {
        $amount = round($amount, 2);
        if ($amount <= 0.009) {
            return null;
        }

        if ($this->hasGlPosted((int) $offer->id)) {
            return null;
        }

        $customerAccount = $this->accountLinking->ensureCustomerCompanyAccount($company);
        if (! $customerAccount) {
            throw new \RuntimeException('تعذر إنشاء/ربط حساب الشجرة لعميل الشركة.');
        }

        $salesAcc = TreeAccount::resolveSalesRevenueAccount();
        if (! $salesAcc) {
            throw new \RuntimeException('حساب إيرادات المبيعات غير موجود في شجرة الحسابات.');
        }

        $batchCode = 'OFFER-' . $offer->id . '-' . now()->format('YmdHis');
        $desc = 'مديونية عرض سعر رقم ' . $offer->id . ' — ' . ($company->name ?? '');

        $this->journal->postBalancedJournal(
            [
                [
                    'account_id' => (int) $customerAccount->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => 'ذمم عميل شركة — عرض سعر رقم ' . $offer->id,
                ],
                [
                    'account_id' => (int) $salesAcc->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => 'إيرادات من عرض سعر رقم ' . $offer->id,
                ],
            ],
            $desc,
            null,
            $batchCode,
            $userId
        );

        Log::info('OfferDebtAccountingService: posted offer debt GL', [
            'offer_id' => $offer->id,
            'company_id' => $company->id,
            'batch_code' => $batchCode,
            'amount' => $amount,
        ]);

        return [
            'batch_code' => $batchCode,
            'customer_account_id' => (int) $customerAccount->id,
            'sales_account_id' => (int) $salesAcc->id,
            'amount' => $amount,
        ];
    }
}
