<?php

namespace App\Console\Commands;

use App\Models\AccountEntry;
use App\Models\Bank;
use App\Models\Order;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\AccountLinkingService;
use App\Services\Accounting\LedgerJournalService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * تعويض قيود الشجرة المفقودة لعمليات التحصيل من العملاء.
 *
 * بسبب خطأ في تمرير التاريخ كان OrderPaymentSourceLedgerService يفشل صامتاً في
 * ترحيل قيد الشجرة (مدين البنك / دائن ذمة العميل) رغم نجاح الحركة التشغيلية في
 * bank_details وتخفيض الرصيد التشغيلي. النتيجة: حساب العميل في الشجرة لا يتصفّر.
 *
 * هذا الأمر يعيد ترحيل القيد المفقود من صفوف bank_details الخاصة بالتحصيل.
 *
 * php artisan collection:backfill-gl [--dry-run] [--order=ID]
 */
class BackfillCollectionGlCommand extends Command
{
    protected $signature = 'collection:backfill-gl
        {--dry-run : عرض دون تعديل}
        {--order= : معالجة طلب واحد فقط برقمه}';

    protected $description = 'تعويض قيود الشجرة المفقودة لعمليات التحصيل من العملاء (مدين البنك / دائن ذمة العميل)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $rows = DB::table('bank_details')
            ->where('type', 'الطلبات')
            ->where('details', 'like', '%تحصيل%')
            ->when($this->option('order'), fn ($q) => $q->where('ref', (string) (int) $this->option('order')))
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('لا توجد حركات تحصيل بنكية للمعالجة.');

            return self::SUCCESS;
        }

        $journal = app(LedgerJournalService::class);
        $accountLinking = app(AccountLinkingService::class);
        $accounting = app(AccountingService::class);

        $affectedAccountIds = [];
        $posted = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $orderId = (int) $row->ref;
            if ($orderId <= 0) {
                continue;
            }

            $order = Order::find($orderId);
            if (! $order) {
                $this->warn("تخطّي: طلب {$row->ref} غير موجود (bank_details #{$row->id})");
                $skipped++;
                continue;
            }

            $amount = round((float) $row->amount, 2);
            if ($amount <= 0.009) {
                $skipped++;
                continue;
            }

            $bank = Bank::find($row->bank_id);
            $cashAccountId = $bank && $bank->asset_id ? (int) $bank->asset_id : 0;
            if ($cashAccountId <= 0) {
                $skipped++;
                continue;
            }

            $customer = $accountLinking->resolveOrderCustomerAccount(
                $order->customer_type ?? 'فرد',
                $order->customer_name,
                $order->customer_phone_1,
                $order->company_id,
                $order->order_source_id ? (int) $order->order_source_id : null,
            );
            if (! $customer) {
                $skipped++;
                continue;
            }
            if ((int) $customer->id === $cashAccountId) {
                $skipped++;
                continue;
            }

            // idempotency صارم: تخطّي إن سبق قيد دائن لحساب العميل بخصوص هذا الطلب
            // (أي مسار تحصيل سابق — COLLECT-* أو ORD-OPS-* أو غيره) حتى لا نُكرّر التخفيض.
            $customerAlreadyCredited = AccountEntry::where('order_id', $orderId)
                ->where('tree_account_id', (int) $customer->id)
                ->where('credit', '>', 0.009)
                ->exists();
            if ($customerAlreadyCredited) {
                $skipped++;
                continue;
            }

            // كما نتخطّى إن سبق تسجيل حركة نقدية في الشجرة على حساب البنك لهذا الطلب
            $bankAlreadyDebited = AccountEntry::where('order_id', $orderId)
                ->where('tree_account_id', $cashAccountId)
                ->where('debit', '>', 0.009)
                ->where('entry_batch_code', 'like', 'ORD-OPS-%')
                ->exists();
            if ($bankAlreadyDebited) {
                $skipped++;
                continue;
            }

            $date = $row->date ? Carbon::parse($row->date) : null;
            $batchCode = 'ORD-OPS-' . $orderId . '-' . $row->id . '-BACKFILL';
            $desc = trim((string) $row->details) . ' — طلب رقم ' . $orderId . ' (تعويض قيد الشجرة)';

            if ($posted < 60) {
                $this->line(sprintf(
                    '#%d | بنك %s (حساب %d) → عميل %s (حساب %d) | مبلغ %s | %s',
                    $orderId,
                    $row->bank_id,
                    $cashAccountId,
                    $customer->name,
                    $customer->id,
                    number_format($amount, 2),
                    $row->date
                ));
            } elseif ($posted === 60) {
                $this->line('... (بقية السطور مخفية — سيظهر الإجمالي في النهاية)');
            }

            $affectedAccountIds[$cashAccountId] = true;
            $affectedAccountIds[(int) $customer->id] = true;

            if (! $dryRun) {
                DB::transaction(function () use ($journal, $cashAccountId, $customer, $amount, $desc, $orderId, $batchCode, $date) {
                    $journal->postBalancedJournal(
                        [
                            [
                                'account_id' => $cashAccountId,
                                'debit' => $amount,
                                'credit' => 0,
                                'description' => $desc . ' — تحصيل/إيداع',
                            ],
                            [
                                'account_id' => (int) $customer->id,
                                'debit' => 0,
                                'credit' => $amount,
                                'description' => $desc . ' — تخفيض ذمة',
                            ],
                        ],
                        $desc,
                        $orderId,
                        $batchCode,
                        null,
                        $date,
                        false,
                    );
                });
            }

            $posted++;
        }

        if (! $dryRun && $affectedAccountIds !== []) {
            foreach (array_keys($affectedAccountIds) as $accountId) {
                try {
                    $accounting->updateAccountHierarchyBalances((int) $accountId);
                } catch (\Throwable $e) {
                    $this->warn('تعذر إعادة بناء رصيد الحساب ' . $accountId . ': ' . $e->getMessage());
                }
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%sتم ترحيل %d قيد تحصيل، وتخطّي %d، وإعادة بناء %d حساب.',
            $dryRun ? '[dry-run] ' : '',
            $posted,
            $skipped,
            count($affectedAccountIds)
        ));

        return self::SUCCESS;
    }
}
