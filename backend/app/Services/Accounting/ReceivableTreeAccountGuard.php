<?php

namespace App\Services\Accounting;

use App\Models\Bank;
use App\Models\Safe;
use App\Models\ServiceAccount;
use App\Models\TreeAccount;
use App\Services\Shipping\UnlinkedReceivableAccountException;

/**
 * يمنع استخدام حسابات مصادر النقد (خزينة / بنك / حساب خدمي) كحسابات ذمم شحن أو تحصيل.
 */
class ReceivableTreeAccountGuard
{
    /** @var array<int, bool> */
    private array $paymentSourceCache = [];

    public function isPaymentSourceTreeAccount(?int $treeAccountId): bool
    {
        if (! $treeAccountId) {
            return false;
        }

        if (array_key_exists($treeAccountId, $this->paymentSourceCache)) {
            return $this->paymentSourceCache[$treeAccountId];
        }

        $account = TreeAccount::find($treeAccountId);
        if (! $account) {
            return $this->paymentSourceCache[$treeAccountId] = false;
        }

        if (in_array((string) $account->detail_type, ['safe', 'bank', 'service_account'], true)) {
            return $this->paymentSourceCache[$treeAccountId] = true;
        }

        if (
            Safe::query()->where('account_id', $treeAccountId)->exists()
            || Bank::query()->where('asset_id', $treeAccountId)->exists()
            || ServiceAccount::query()->where('account_id', $treeAccountId)->exists()
        ) {
            return $this->paymentSourceCache[$treeAccountId] = true;
        }

        return $this->paymentSourceCache[$treeAccountId] = false;
    }

    /**
     * يُرجع null إذا كان الحساب غير صالح كذمة (غير موجود أو مربوط بمصدر نقد).
     */
    public function sanitizeReceivableAccountId(?int $accountId): ?int
    {
        if (! $accountId || $this->isPaymentSourceTreeAccount($accountId)) {
            return null;
        }

        return $accountId;
    }

    /**
     * @throws UnlinkedReceivableAccountException
     */
    public function assertReceivableNotPaymentSource(?int $accountId, string $entityLabel): void
    {
        if (! $accountId || ! $this->isPaymentSourceTreeAccount($accountId)) {
            return;
        }

        $account = TreeAccount::find($accountId);
        $label = $account
            ? sprintf('«%s» (%s)', $account->name, $account->code)
            : ('#' . $accountId);

        throw new UnlinkedReceivableAccountException(
            $entityLabel . ' مربوطة بحساب مصدر نقد ' . $label
            . ' — يجب ربطها بحساب ذمم منفصل تحت «شركات الشحن والمناديب» أو «شركات التحصيل»، وليس حساب خزينة أو بنك.'
        );
    }

    /**
     * @throws UnlinkedReceivableAccountException
     */
    public function assertValidReceivableAssignment(?int $accountId, string $fieldLabel): void
    {
        if (! $accountId) {
            return;
        }

        if (! TreeAccount::find($accountId)) {
            throw new UnlinkedReceivableAccountException('الحساب المحدد لـ ' . $fieldLabel . ' غير موجود.');
        }

        $this->assertReceivableNotPaymentSource($accountId, $fieldLabel);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function assertValidPaymentSourceTreeAccount(int $accountId): void
    {
        if (! $accountId) {
            throw new \InvalidArgumentException('يجب تحديد حساب خزينة أو بنك أو حساب خدمي مرتبطاً بشجرة الحسابات.');
        }

        if (! $this->isPaymentSourceTreeAccount($accountId)) {
            throw new \InvalidArgumentException(
                'حساب السند المختار ليس خزينة أو بنك أو حساب خدمي مرتبطاً بشجرة الحسابات.'
            );
        }
    }
}
