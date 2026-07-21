<?php

namespace App\Services\Accounting;

use App\Models\AccountEntry;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\TreeAccount;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * تسوية رصيد حساب شجري عبر قيد يومي (مدين/دائن) مع حساب مقابل — بدون تعديل صامت للأرصدة.
 */
class ManualBalanceAdjustmentService
{
    /** بادئة كود الدفعة لقيود الرصيد الافتتاحي (تُستخدم للاستبدال عند التعديل) */
    public const OPENING_BATCH_PREFIX = 'OPENING-BAL-';

    public function __construct(
        private AccountingService $accountingService,
        private BudgetReviewService $budgetService
    ) {}

    /**
     * يضبط صافي رصيد الحساب (مدين − دائن) ليطابق target_balance بناءً على مجموع القيود الحالي.
     *
     * @return array{daily_entry: DailyEntry, previous_net: float, delta: float, target_balance: float}
     */
    public function adjustToTarget(
        TreeAccount $account,
        float $targetBalance,
        TreeAccount $counterAccount,
        ?string $userReason,
        string $dateYmd,
        int $userId
    ): array {
        if ($account->id === $counterAccount->id) {
            throw new \InvalidArgumentException('الحساب المقابل يجب أن يختلف عن الحساب المُعدَّل.');
        }

        return DB::transaction(function () use ($account, $targetBalance, $counterAccount, $userReason, $dateYmd, $userId) {
            $account->refresh();
            $counterAccount->refresh();

            $totalDebit = (float) AccountEntry::where('tree_account_id', $account->id)->sum('debit');
            $totalCredit = (float) AccountEntry::where('tree_account_id', $account->id)->sum('credit');
            $previousNet = round($totalDebit - $totalCredit, 2);
            $target = round($targetBalance, 2);
            $delta = round($target - $previousNet, 2);

            if (abs($delta) < 0.01) {
                throw new \InvalidArgumentException('الرصيد الحالي (من القيود) يطابق المستهدف — لا حاجة لقيد.');
            }

            $absDelta = abs($delta);
            if ($delta > 0) {
                $adjDebit = $absDelta;
                $adjCredit = 0.0;
                $cntDebit = 0.0;
                $cntCredit = $absDelta;
            } else {
                $adjDebit = 0.0;
                $adjCredit = $absDelta;
                $cntDebit = $absDelta;
                $cntCredit = 0.0;
            }

            $itemsForBudget = [
                ['tree_account_id' => $account->id, 'debit' => $adjDebit, 'credit' => $adjCredit],
                ['tree_account_id' => $counterAccount->id, 'debit' => $cntDebit, 'credit' => $cntCredit],
            ];
            $budgetResult = $this->budgetService->checkBudget($itemsForBudget, $dateYmd);
            if (!$budgetResult['valid']) {
                throw new \RuntimeException($budgetResult['message']);
            }

            $user = User::find($userId);
            $userLabel = $user?->name ?? $user?->email ?? ('#' . $userId);

            $lineDescription = $this->buildAuditDescription(
                $userLabel,
                $account,
                $counterAccount,
                $previousNet,
                $target,
                $delta,
                $userReason
            );

            $headerDescription = $userReason
                ? $userReason
                : 'تسوية رصيد — ' . $account->code . ' ' . $account->name;

            $entryNumber = DailyEntry::getNextEntryNumber();
            $at = Carbon::parse($dateYmd)->setTime(12, 0, 0);

            $dailyEntry = DailyEntry::create([
                'date' => $dateYmd,
                'entry_number' => $entryNumber,
                'description' => $headerDescription,
                'user_id' => $userId,
            ]);

            DailyEntryItem::create([
                'daily_entry_id' => $dailyEntry->id,
                'account_id' => $account->id,
                'debit' => $adjDebit,
                'credit' => $adjCredit,
                'notes' => $lineDescription,
            ]);
            DailyEntryItem::create([
                'daily_entry_id' => $dailyEntry->id,
                'account_id' => $counterAccount->id,
                'debit' => $cntDebit,
                'credit' => $cntCredit,
                'notes' => $lineDescription,
            ]);

            AccountEntry::create([
                'tree_account_id' => $account->id,
                'debit' => $adjDebit,
                'credit' => $adjCredit,
                'description' => $lineDescription,
                'daily_entry_id' => $dailyEntry->id,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
            AccountEntry::create([
                'tree_account_id' => $counterAccount->id,
                'debit' => $cntDebit,
                'credit' => $cntCredit,
                'description' => $lineDescription,
                'daily_entry_id' => $dailyEntry->id,
                'created_at' => $at,
                'updated_at' => $at,
            ]);

            $this->accountingService->updateAccountHierarchyBalances($account->id);
            $this->accountingService->updateAccountHierarchyBalances($counterAccount->id);

            $dailyEntry->load(['items.account', 'user']);

            return [
                'daily_entry' => $dailyEntry,
                'previous_net' => $previousNet,
                'delta' => $delta,
                'target_balance' => $target,
            ];
        });
    }

    /**
     * يضبط الرصيد الافتتاحي للحساب عند تاريخ محدد: يجعل صافي الرصيد (مدين−دائن) عند تاريخ
     * الافتتاح = target، والعمليات اللاحقة تمشي فوقه (الفرق يُحسب من القيود قبل التاريخ فقط).
     * قابل للتعديل: يستبدل قيد الافتتاح السابق لنفس الحساب (إن وُجد) بدل تكديس القيود.
     *
     * @return array{daily_entry: ?DailyEntry, prior_net: float, delta: float, target_balance: float, replaced_previous: bool}
     */
    public function setOpeningBalance(
        TreeAccount $account,
        float $targetBalance,
        TreeAccount $counterAccount,
        ?string $userReason,
        string $dateYmd,
        int $userId
    ): array {
        if ($account->id === $counterAccount->id) {
            throw new \InvalidArgumentException('الحساب المقابل يجب أن يختلف عن الحساب المُعدَّل.');
        }

        return DB::transaction(function () use ($account, $targetBalance, $counterAccount, $userReason, $dateYmd, $userId) {
            $account->refresh();
            $counterAccount->refresh();

            $batchCode = self::OPENING_BATCH_PREFIX . $account->id;
            $affectedAccountIds = [$account->id, $counterAccount->id];
            $replacedPrevious = false;

            // 1) احذف قيد الافتتاح السابق لنفس الحساب (إن وُجد) — يجعل التعديل idempotent
            $oldEntries = AccountEntry::where('entry_batch_code', $batchCode)->get();
            if ($oldEntries->isNotEmpty()) {
                $replacedPrevious = true;
                $oldDailyIds = $oldEntries->pluck('daily_entry_id')->filter()->unique()->values()->all();
                foreach ($oldEntries as $oe) {
                    $affectedAccountIds[] = (int) $oe->tree_account_id;
                }
                AccountEntry::where('entry_batch_code', $batchCode)->delete();
                if ($oldDailyIds) {
                    DailyEntryItem::whereIn('daily_entry_id', $oldDailyIds)->delete();
                    DailyEntry::whereIn('id', $oldDailyIds)->delete();
                }
            }

            // 2) صافي القيود قبل تاريخ الافتتاح (بالتاريخ الفعلي COALESCE(daily_entry, voucher, created_at))
            $priorQuery = AccountEntry::query()
                ->leftJoin('daily_entries as de', 'account_entries.daily_entry_id', '=', 'de.id')
                ->leftJoin('vouchers as v', 'account_entries.voucher_id', '=', 'v.id')
                ->where('account_entries.tree_account_id', $account->id)
                ->whereRaw('DATE(COALESCE(de.date, v.date, account_entries.created_at)) < ?', [$dateYmd]);
            $priorDebit = (float) (clone $priorQuery)->sum('account_entries.debit');
            $priorCredit = (float) (clone $priorQuery)->sum('account_entries.credit');
            $priorNet = round($priorDebit - $priorCredit, 2);

            $target = round($targetBalance, 2);
            $delta = round($target - $priorNet, 2);

            $dailyEntry = null;

            if (abs($delta) >= 0.01) {
                $absDelta = abs($delta);
                if ($delta > 0) {
                    $adjDebit = $absDelta;
                    $adjCredit = 0.0;
                    $cntDebit = 0.0;
                    $cntCredit = $absDelta;
                } else {
                    $adjDebit = 0.0;
                    $adjCredit = $absDelta;
                    $cntDebit = $absDelta;
                    $cntCredit = 0.0;
                }

                $itemsForBudget = [
                    ['tree_account_id' => $account->id, 'debit' => $adjDebit, 'credit' => $adjCredit],
                    ['tree_account_id' => $counterAccount->id, 'debit' => $cntDebit, 'credit' => $cntCredit],
                ];
                $budgetResult = $this->budgetService->checkBudget($itemsForBudget, $dateYmd);
                if (!$budgetResult['valid']) {
                    throw new \RuntimeException($budgetResult['message']);
                }

                $user = User::find($userId);
                $userLabel = $user?->name ?? $user?->email ?? ('#' . $userId);

                $lineDescription = $this->buildOpeningDescription(
                    $userLabel,
                    $account,
                    $counterAccount,
                    $priorNet,
                    $target,
                    $delta,
                    $dateYmd,
                    $userReason
                );
                $headerDescription = $userReason
                    ? $userReason
                    : 'رصيد افتتاحي — ' . $account->code . ' ' . $account->name . ' — بتاريخ ' . $dateYmd;

                $entryNumber = DailyEntry::getNextEntryNumber();
                $at = Carbon::parse($dateYmd)->setTime(12, 0, 0);

                $dailyEntry = DailyEntry::create([
                    'date' => $dateYmd,
                    'entry_number' => $entryNumber,
                    'description' => $headerDescription,
                    'user_id' => $userId,
                ]);

                DailyEntryItem::create([
                    'daily_entry_id' => $dailyEntry->id,
                    'account_id' => $account->id,
                    'debit' => $adjDebit,
                    'credit' => $adjCredit,
                    'notes' => $lineDescription,
                ]);
                DailyEntryItem::create([
                    'daily_entry_id' => $dailyEntry->id,
                    'account_id' => $counterAccount->id,
                    'debit' => $cntDebit,
                    'credit' => $cntCredit,
                    'notes' => $lineDescription,
                ]);

                AccountEntry::create([
                    'tree_account_id' => $account->id,
                    'debit' => $adjDebit,
                    'credit' => $adjCredit,
                    'description' => $lineDescription,
                    'daily_entry_id' => $dailyEntry->id,
                    'entry_batch_code' => $batchCode,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);
                AccountEntry::create([
                    'tree_account_id' => $counterAccount->id,
                    'debit' => $cntDebit,
                    'credit' => $cntCredit,
                    'description' => $lineDescription,
                    'daily_entry_id' => $dailyEntry->id,
                    'entry_batch_code' => $batchCode,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);
            }

            // 3) أعد حساب أرصدة كل الحسابات المتأثرة (بما فيها الحساب المقابل القديم إن تغيّر)
            foreach (array_values(array_unique($affectedAccountIds)) as $aid) {
                $this->accountingService->updateAccountHierarchyBalances((int) $aid);
            }

            if ($dailyEntry) {
                $dailyEntry->load(['items.account', 'user']);
            }

            return [
                'daily_entry' => $dailyEntry,
                'prior_net' => $priorNet,
                'delta' => $delta,
                'target_balance' => $target,
                'replaced_previous' => $replacedPrevious,
            ];
        });
    }

    /**
     * يُرجع معلومات الرصيد الافتتاحي المسجّل حالياً للحساب (إن وُجد) لعرضها قبل التعديل.
     *
     * @return array{exists: bool, date?: string, target_net?: float}
     */
    public function getOpeningBalanceInfo(TreeAccount $account): array
    {
        $batchCode = self::OPENING_BATCH_PREFIX . $account->id;

        $entry = AccountEntry::query()
            ->leftJoin('daily_entries as de', 'account_entries.daily_entry_id', '=', 'de.id')
            ->where('account_entries.tree_account_id', $account->id)
            ->where('account_entries.entry_batch_code', $batchCode)
            ->select('account_entries.*', 'de.date as de_date')
            ->first();

        if (! $entry) {
            return ['exists' => false];
        }

        $dateYmd = $entry->de_date
            ? Carbon::parse($entry->de_date)->format('Y-m-d')
            : Carbon::parse($entry->created_at)->format('Y-m-d');

        $priorQuery = AccountEntry::query()
            ->leftJoin('daily_entries as de', 'account_entries.daily_entry_id', '=', 'de.id')
            ->leftJoin('vouchers as v', 'account_entries.voucher_id', '=', 'v.id')
            ->where('account_entries.tree_account_id', $account->id)
            ->whereRaw('DATE(COALESCE(de.date, v.date, account_entries.created_at)) < ?', [$dateYmd]);
        $priorDebit = (float) (clone $priorQuery)->sum('account_entries.debit');
        $priorCredit = (float) (clone $priorQuery)->sum('account_entries.credit');
        $priorNet = round($priorDebit - $priorCredit, 2);

        $openingNet = round((float) $entry->debit - (float) $entry->credit, 2);

        return [
            'exists' => true,
            'date' => $dateYmd,
            'target_net' => round($priorNet + $openingNet, 2),
        ];
    }

    /**
     * مطابقة عدة أرصدة في قيد يومي واحد (نفس الحساب المقابل لكل فرق) — مناسب عندما الأرصدة الحالية من القيود = 0.
     *
     * @param  array<int, float>  $targetsByAccountId  [ tree_account_id => target_balance (مدين − دائن) ]
     * @return array{daily_entry: DailyEntry, posted: array<int, array{previous_net: float, delta: float, target_balance: float}>}
     */
    public function adjustBatchToTargets(
        array $targetsByAccountId,
        TreeAccount $counterAccount,
        ?string $userReason,
        string $dateYmd,
        int $userId
    ): array {
        if ($targetsByAccountId === []) {
            throw new \InvalidArgumentException('لا توجد حسابات في القائمة.');
        }

        return DB::transaction(function () use ($targetsByAccountId, $counterAccount, $userReason, $dateYmd, $userId) {
            $counterAccount->refresh();
            $counterId = $counterAccount->id;

            $posted = [];
            $budgetChunks = [];
            $at = Carbon::parse($dateYmd)->setTime(12, 0, 0);
            $user = User::find($userId);
            $userLabel = $user?->name ?? $user?->email ?? ('#' . $userId);

            foreach ($targetsByAccountId as $accountId => $targetBalance) {
                $accountId = (int) $accountId;
                if ($accountId === $counterId) {
                    throw new \InvalidArgumentException('لا يمكن تضمين الحساب المقابل (' . $counterId . ') ضمن الحسابات المُعدَّلة في نفس القيد.');
                }

                $account = TreeAccount::findOrFail($accountId);
                $totalDebit = (float) AccountEntry::where('tree_account_id', $accountId)->sum('debit');
                $totalCredit = (float) AccountEntry::where('tree_account_id', $accountId)->sum('credit');
                $previousNet = round($totalDebit - $totalCredit, 2);
                $target = round((float) $targetBalance, 2);
                $delta = round($target - $previousNet, 2);

                if (abs($delta) < 0.01) {
                    continue;
                }

                $absDelta = abs($delta);
                if ($delta > 0) {
                    $adjDebit = $absDelta;
                    $adjCredit = 0.0;
                    $cntDebit = 0.0;
                    $cntCredit = $absDelta;
                } else {
                    $adjDebit = 0.0;
                    $adjCredit = $absDelta;
                    $cntDebit = $absDelta;
                    $cntCredit = 0.0;
                }

                $budgetChunks[] = ['tree_account_id' => $accountId, 'debit' => $adjDebit, 'credit' => $adjCredit];
                $budgetChunks[] = ['tree_account_id' => $counterId, 'debit' => $cntDebit, 'credit' => $cntCredit];

                $lineDescription = $this->buildAuditDescription(
                    $userLabel,
                    $account,
                    $counterAccount,
                    $previousNet,
                    $target,
                    $delta,
                    $userReason
                );

                $posted[$accountId] = [
                    'previous_net' => $previousNet,
                    'delta' => $delta,
                    'target_balance' => $target,
                    '_adjDebit' => $adjDebit,
                    '_adjCredit' => $adjCredit,
                    '_cntDebit' => $cntDebit,
                    '_cntCredit' => $cntCredit,
                    '_lineDescription' => $lineDescription,
                ];
            }

            if ($posted === []) {
                throw new \InvalidArgumentException('كل الأرصدة المطلوبة تطابق القيود الحالية — لا حاجة لقيد.');
            }

            $budgetResult = $this->budgetService->checkBudget($budgetChunks, $dateYmd);
            if (!$budgetResult['valid']) {
                throw new \RuntimeException($budgetResult['message']);
            }

            $entryNumber = DailyEntry::getNextEntryNumber();
            $headerDescription = $userReason
                ? $userReason
                : 'مطابقة أرصدة مع الواقع — قيد موحّد (' . count($posted) . ' حساب)';

            $dailyEntry = DailyEntry::create([
                'date' => $dateYmd,
                'entry_number' => $entryNumber,
                'description' => $headerDescription,
                'user_id' => $userId,
            ]);

            $touchedIds = [$counterId];

            foreach ($posted as $accountId => $row) {
                DailyEntryItem::create([
                    'daily_entry_id' => $dailyEntry->id,
                    'account_id' => $accountId,
                    'debit' => $row['_adjDebit'],
                    'credit' => $row['_adjCredit'],
                    'notes' => $row['_lineDescription'],
                ]);
                DailyEntryItem::create([
                    'daily_entry_id' => $dailyEntry->id,
                    'account_id' => $counterId,
                    'debit' => $row['_cntDebit'],
                    'credit' => $row['_cntCredit'],
                    'notes' => $row['_lineDescription'],
                ]);

                AccountEntry::create([
                    'tree_account_id' => $accountId,
                    'debit' => $row['_adjDebit'],
                    'credit' => $row['_adjCredit'],
                    'description' => $row['_lineDescription'],
                    'daily_entry_id' => $dailyEntry->id,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);
                AccountEntry::create([
                    'tree_account_id' => $counterId,
                    'debit' => $row['_cntDebit'],
                    'credit' => $row['_cntCredit'],
                    'description' => $row['_lineDescription'],
                    'daily_entry_id' => $dailyEntry->id,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);

                $touchedIds[] = $accountId;

                unset(
                    $posted[$accountId]['_adjDebit'],
                    $posted[$accountId]['_adjCredit'],
                    $posted[$accountId]['_cntDebit'],
                    $posted[$accountId]['_cntCredit'],
                    $posted[$accountId]['_lineDescription']
                );
            }

            $touchedIds = array_values(array_unique($touchedIds));
            foreach ($touchedIds as $aid) {
                $this->accountingService->updateAccountHierarchyBalances((int) $aid);
            }

            $dailyEntry->load(['items.account', 'user']);

            return [
                'daily_entry' => $dailyEntry,
                'posted' => $posted,
            ];
        });
    }

    private function buildAuditDescription(
        string $userLabel,
        TreeAccount $account,
        TreeAccount $counter,
        float $previousNet,
        float $target,
        float $delta,
        ?string $userReason
    ): string {
        $base = sprintf(
            'تسوية رصيد — تم التعديل من قبل المستخدم «%s» — الحساب المستهدف: [%s] %s — صافي الرصيد من القيود قبل التعديل: %s — المستهدف: %s — الفرق المسجّل في القيد: %s — الحساب المقابل: [%s] %s',
            $userLabel,
            $account->code,
            $account->name,
            number_format($previousNet, 2, '.', ''),
            number_format($target, 2, '.', ''),
            number_format($delta, 2, '.', ''),
            $counter->code,
            $counter->name
        );
        if ($userReason !== null && trim($userReason) !== '') {
            return $base . ' — ملاحظة المستخدم: ' . trim($userReason);
        }

        return $base . ' — (لم يُذكر سبب يدوي؛ وصف تلقائي للتتبع المحاسبي)';
    }

    private function buildOpeningDescription(
        string $userLabel,
        TreeAccount $account,
        TreeAccount $counter,
        float $priorNet,
        float $target,
        float $delta,
        string $dateYmd,
        ?string $userReason
    ): string {
        $base = sprintf(
            'رصيد افتتاحي بتاريخ %s — تم التسجيل من قبل المستخدم «%s» — الحساب: [%s] %s — صافي القيود قبل التاريخ: %s — الرصيد الافتتاحي المستهدف: %s — الفرق المسجّل: %s — الحساب المقابل: [%s] %s',
            $dateYmd,
            $userLabel,
            $account->code,
            $account->name,
            number_format($priorNet, 2, '.', ''),
            number_format($target, 2, '.', ''),
            number_format($delta, 2, '.', ''),
            $counter->code,
            $counter->name
        );
        if ($userReason !== null && trim($userReason) !== '') {
            return $base . ' — ملاحظة المستخدم: ' . trim($userReason);
        }

        return $base . ' — (رصيد افتتاحي — العمليات اللاحقة تُحسب فوقه)';
    }
}
