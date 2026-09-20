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

    /**
     * قيد تسوية فرق مديونية العرض بعد تعديل الإجمالي (زيادة أو تخفيض).
     *
     * @return array{batch_code: string, amount: float}|null
     */
    public function adjustOfferDebt(Offers $offer, customerCompany $company, float $delta, ?int $userId = null): ?array
    {
        $delta = round($delta, 2);
        if (abs($delta) < 0.01) {
            return null;
        }

        if (! $this->hasGlPosted((int) $offer->id)) {
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

        $amount = abs($delta);
        $increase = $delta > 0;
        $batchCode = 'OFFER-' . $offer->id . '-ADJ-' . now()->format('YmdHis');
        $desc = ($increase ? 'زيادة' : 'تخفيض')
            . ' مديونية عرض سعر رقم ' . $offer->id
            . ' — ' . ($company->name ?? '');

        $this->journal->postBalancedJournal(
            $increase
                ? [
                    [
                        'account_id' => (int) $customerAccount->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'description' => 'زيادة ذمم عميل شركة — عرض سعر رقم ' . $offer->id,
                    ],
                    [
                        'account_id' => (int) $salesAcc->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'description' => 'زيادة إيرادات من عرض سعر رقم ' . $offer->id,
                    ],
                ]
                : [
                    [
                        'account_id' => (int) $salesAcc->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'description' => 'تخفيض إيرادات من عرض سعر رقم ' . $offer->id,
                    ],
                    [
                        'account_id' => (int) $customerAccount->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'description' => 'تخفيض ذمم عميل شركة — عرض سعر رقم ' . $offer->id,
                    ],
                ],
            $desc,
            null,
            $batchCode,
            $userId
        );

        Log::info('OfferDebtAccountingService: adjusted offer debt GL', [
            'offer_id' => $offer->id,
            'company_id' => $company->id,
            'batch_code' => $batchCode,
            'delta' => $delta,
        ]);

        return [
            'batch_code' => $batchCode,
            'amount' => $delta,
        ];
    }
}
