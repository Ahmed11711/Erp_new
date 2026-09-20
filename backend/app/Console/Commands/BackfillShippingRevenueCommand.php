<?php

namespace App\Console\Commands;

use App\Models\AccountEntry;
use App\Models\DailyEntryItem;
use App\Models\Order;
use App\Models\TreeAccount;
use App\Services\Accounting\AccountingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * إعادة تصنيف إيراد الشحن من حساب المبيعات إلى حساب إيرادات الشحن للطلبات القديمة.
 *
 * الحالات المغطاة:
 * 1) سطر دائن منفصل بوصف «إيراد شحن…» على حساب المبيعات → يُنقل لحساب إيراد الشحن
 * 2) الشحن مدمج داخل دائن المبيعات بدون سطر منفصل → يُفصل المبلغ إلى سطر جديد
 *
 * لا يمس ذمم العميل ولا قيود التحصيل (COLLECT-* / PARTCOLLECT-* / ORD-OPS-*).
 *
 * php artisan accounting:backfill-shipping-revenue --dry-run
 * php artisan accounting:backfill-shipping-revenue --from=2026-01-01 --to=2026-07-19
 * php artisan accounting:backfill-shipping-revenue --order=45008
 */
class BackfillShippingRevenueCommand extends Command
{
    protected $signature = 'accounting:backfill-shipping-revenue
        {--dry-run : عرض دون تعديل}
        {--from= : تاريخ الطلب من (Y-m-d)}
        {--to= : تاريخ الطلب إلى (Y-m-d)}
        {--order= : معالجة طلب واحد فقط}
        {--limit=500 : أقصى عدد طلبات في التشغيل الواحد}';

    protected $description = 'إعادة تصنيف إيراد الشحن من المبيعات إلى حساب إيرادات الشحن للطلبات القديمة';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));

        $shippingAcc = TreeAccount::resolveShippingRevenueAccount();
        if (! $shippingAcc) {
            $this->error('تعذّر إيجاد حساب إيراد الشحن. تأكد من وجود حساب تفصيلي باسم مثل «إيرادات الشحن» أو detail_type=shipping_revenue.');

            return self::FAILURE;
        }

        $salesAcc = TreeAccount::resolveSalesRevenueAccount();
        if (! $salesAcc) {
            $this->error('تعذّر إيجاد حساب إيرادات المبيعات.');

            return self::FAILURE;
        }

        if ((int) $shippingAcc->id === (int) $salesAcc->id) {
            $this->error('حساب إيراد الشحن هو نفسه حساب المبيعات — أوقف التشغيل وصحّح ربط الحسابات أولاً.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'حساب إيراد الشحن: #%d %s (%s)',
            $shippingAcc->id,
            $shippingAcc->name,
            $shippingAcc->code
        ));
        $this->info(sprintf(
            'حساب المبيعات (مرجع): #%d %s (%s)',
            $salesAcc->id,
            $salesAcc->name,
            $salesAcc->code
        ));

        $orders = $this->candidateOrders($limit);
        if ($orders->isEmpty()) {
            $this->info('لا توجد طلبات تحتاج إعادة تصنيف إيراد الشحن.');

            return self::SUCCESS;
        }

        $accounting = app(AccountingService::class);
        $affectedAccountIds = [
            (int) $shippingAcc->id => true,
            (int) $salesAcc->id => true,
        ];
        $fixed = 0;
        $skipped = 0;
        $movedTotal = 0.0;

        foreach ($orders as $order) {
            $shippingExpected = round((float) ($order->shipping_revenue ?? $order->shipping_cost ?? 0), 2);
            if ($shippingExpected <= 0.009) {
                $skipped++;
                continue;
            }

            $pattern = 'ORD-' . $order->id . '-%';
            $entries = AccountEntry::query()
                ->where('order_id', $order->id)
                ->where('entry_batch_code', 'like', $pattern)
                ->orderBy('id')
                ->get();

            if ($entries->isEmpty()) {
                $skipped++;
                continue;
            }

            $alreadyOnShipping = round((float) $entries
                ->where('tree_account_id', (int) $shippingAcc->id)
                ->sum('credit'), 2);

            if ($alreadyOnShipping + 0.009 >= $shippingExpected) {
                $skipped++;
                continue;
            }

            $need = round($shippingExpected - $alreadyOnShipping, 2);
            $result = $this->planReclass($entries, $salesAcc->id, $shippingAcc->id, $need);

            if ($result['amount'] <= 0.009) {
                $this->warn(sprintf(
                    '#%d: شحن متوقع %s لكن لم يُعثر على سطر مبيعات قابل للنقل (تخطي)',
                    $order->id,
                    number_format($shippingExpected, 2)
                ));
                $skipped++;
                continue;
            }

            $this->line(sprintf(
                '#%d | شحن %s | طريقة: %s | نقل %s',
                $order->id,
                number_format($shippingExpected, 2),
                $result['mode'],
                number_format($result['amount'], 2)
            ));

            $fixed++;
            $movedTotal += $result['amount'];

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($result, $shippingAcc, $order, &$affectedAccountIds) {
                foreach ($result['moves'] as $move) {
                    /** @var AccountEntry $entry */
                    $entry = $move['entry'];
                    $fromAccountId = (int) $entry->tree_account_id;
                    $affectedAccountIds[$fromAccountId] = true;
                    $affectedAccountIds[(int) $shippingAcc->id] = true;

                    if ($move['action'] === 'reclass') {
                        $entry->tree_account_id = (int) $shippingAcc->id;
                        if (is_string($entry->description) && str_contains($entry->description, 'لم يُعثر')) {
                            $entry->description = 'إيراد شحن وتوصيل محصل من العميل (تصحيح رجعي)';
                        }
                        $entry->save();

                        $this->syncDailyEntryItemAccount(
                            $entry,
                            $fromAccountId,
                            (int) $shippingAcc->id,
                            (float) $entry->credit,
                            $entry->description
                        );
                    } elseif ($move['action'] === 'split') {
                        $reduce = round((float) $move['amount'], 2);
                        $entry->credit = round((float) $entry->credit - $reduce, 2);
                        $entry->save();

                        $this->reduceDailyEntryItemCredit(
                            $entry,
                            $fromAccountId,
                            $reduce
                        );

                        $newEntry = AccountEntry::create([
                            'tree_account_id' => (int) $shippingAcc->id,
                            'debit' => 0,
                            'credit' => $reduce,
                            'description' => 'إيراد شحن وتوصيل محصل من العميل (تصحيح رجعي)',
                            'order_id' => $order->id,
                            'entry_batch_code' => $entry->entry_batch_code,
                            'daily_entry_id' => $entry->daily_entry_id,
                            'created_at' => $entry->created_at ?? now(),
                            'updated_at' => now(),
                        ]);

                        if ($entry->daily_entry_id) {
                            DailyEntryItem::create([
                                'daily_entry_id' => (int) $entry->daily_entry_id,
                                'account_id' => (int) $shippingAcc->id,
                                'debit' => 0,
                                'credit' => $reduce,
                                'notes' => $newEntry->description,
                            ]);
                        }
                    }
                }
            });
        }

        if (! $dryRun && $fixed > 0) {
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
            '%sطلبات مصحّحة: %d | متخطّاة: %d | إجمالي إيراد شحن مُعاد تصنيفه: %s',
            $dryRun ? '[dry-run] ' : '',
            $fixed,
            $skipped,
            number_format($movedTotal, 2)
        ));

        if ($dryRun && $fixed > 0) {
            $this->comment('للتنفيذ الفعلي احذف --dry-run');
        }

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Order>
     */
    private function candidateOrders(int $limit)
    {
        $shippingAcc = TreeAccount::resolveShippingRevenueAccount();
        $shippingId = (int) ($shippingAcc?->id ?? 0);

        $q = Order::query()
            ->where(function ($w) {
                $w->whereNull('offer_debt_posted')
                    ->orWhere('offer_debt_posted', 0);
            })
            ->whereNotIn('order_status', ['ملغي', 'أرشيف'])
            ->whereRaw('COALESCE(shipping_revenue, shipping_cost, 0) > 0.009')
            ->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('account_entries as ae')
                    ->whereColumn('ae.order_id', 'orders.id')
                    ->whereRaw("ae.entry_batch_code LIKE CONCAT('ORD-', orders.id, '-%')");
            });

        if ($shippingId > 0) {
            // طلبات لم يُرحَّل عليها كامل إيراد الشحن على الحساب الصحيح
            $q->whereRaw(
                '(
                    SELECT COALESCE(SUM(ae2.credit), 0)
                    FROM account_entries ae2
                    WHERE ae2.order_id = orders.id
                      AND ae2.entry_batch_code LIKE CONCAT(\'ORD-\', orders.id, \'-%\')
                      AND ae2.tree_account_id = ?
                ) < COALESCE(orders.shipping_revenue, orders.shipping_cost, 0) - 0.009',
                [$shippingId]
            );
        }

        if ($this->option('order')) {
            $q->where('id', (int) $this->option('order'));
        }
        if ($this->option('from')) {
            $q->whereDate('order_date', '>=', (string) $this->option('from'));
        }
        if ($this->option('to')) {
            $q->whereDate('order_date', '<=', (string) $this->option('to'));
        }

        return $q->orderBy('id')
            ->limit($limit)
            ->get(['id', 'order_date', 'shipping_revenue', 'shipping_cost', 'order_status']);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AccountEntry>  $entries
     * @return array{mode: string, amount: float, moves: list<array{action: string, entry: AccountEntry, amount: float}>}
     */
    private function planReclass($entries, int $salesAccId, int $shippingAccId, float $need): array
    {
        $moves = [];
        $remaining = $need;

        // 1) أسطر وصفها إيراد شحن لكنها ليست على حساب إيراد الشحن
        $shippingDescLines = $entries->filter(function (AccountEntry $e) use ($shippingAccId) {
            if ((float) $e->credit <= 0.009) {
                return false;
            }
            if ((int) $e->tree_account_id === $shippingAccId) {
                return false;
            }
            $desc = (string) ($e->description ?? '');

            return $this->looksLikeShippingRevenueDescription($desc);
        })->values();

        foreach ($shippingDescLines as $entry) {
            if ($remaining <= 0.009) {
                break;
            }
            $credit = round((float) $entry->credit, 2);
            $take = min($remaining, $credit);
            if (abs($take - $credit) <= 0.009) {
                $moves[] = ['action' => 'reclass', 'entry' => $entry, 'amount' => $credit];
                $remaining = round($remaining - $credit, 2);
            } else {
                // نادراً: السطر أكبر من المطلوب — نفصل الجزء فقط
                $moves[] = ['action' => 'split', 'entry' => $entry, 'amount' => $take];
                $remaining = round($remaining - $take, 2);
            }
        }

        // 2) لا يوجد سطر وصف شحن: افصل من أكبر دائن على حساب المبيعات
        if ($remaining > 0.009) {
            $salesCredits = $entries
                ->filter(fn (AccountEntry $e) => (int) $e->tree_account_id === $salesAccId && (float) $e->credit > 0.009)
                ->sortByDesc(fn (AccountEntry $e) => (float) $e->credit)
                ->values();

            foreach ($salesCredits as $entry) {
                if ($remaining <= 0.009) {
                    break;
                }
                // لا تقطع سطراً وُصف كبضاعة فقط إن وُجد سطر شحن منفصل سابقاً ولم يكفِ
                $desc = (string) ($entry->description ?? '');
                if ($this->looksLikeProductSalesOnlyDescription($desc) && $shippingDescLines->isNotEmpty()) {
                    continue;
                }

                $credit = round((float) $entry->credit, 2);
                $take = min($remaining, $credit);
                if ($take <= 0.009) {
                    continue;
                }
                if (abs($take - $credit) <= 0.009 && $this->looksLikeShippingRevenueDescription($desc)) {
                    $moves[] = ['action' => 'reclass', 'entry' => $entry, 'amount' => $credit];
                } else {
                    $moves[] = ['action' => 'split', 'entry' => $entry, 'amount' => $take];
                }
                $remaining = round($remaining - $take, 2);
            }
        }

        $amount = round($need - max(0, $remaining), 2);
        $mode = 'none';
        if ($moves !== []) {
            $actions = collect($moves)->pluck('action')->unique()->values()->all();
            $mode = count($actions) === 1 ? $actions[0] : 'mixed';
        }

        return [
            'mode' => $mode,
            'amount' => $amount,
            'moves' => $moves,
        ];
    }

    private function looksLikeShippingRevenueDescription(string $desc): bool
    {
        $d = mb_strtolower($desc);

        return str_contains($d, 'إيراد شحن')
            || str_contains($d, 'ايراد شحن')
            || str_contains($d, 'إيرادات الشحن')
            || str_contains($d, 'ايرادات الشحن')
            || str_contains($d, 'شحن وتوصيل')
            || str_contains($d, 'shipping revenue')
            || str_contains($d, 'shipping & handling');
    }

    private function looksLikeProductSalesOnlyDescription(string $desc): bool
    {
        $d = mb_strtolower($desc);

        return (str_contains($d, 'إيرادات المبيعات') || str_contains($d, 'بضاعة') || str_contains($d, 'مبيعات'))
            && ! $this->looksLikeShippingRevenueDescription($desc);
    }

    private function syncDailyEntryItemAccount(
        AccountEntry $entry,
        int $fromAccountId,
        int $toAccountId,
        float $credit,
        ?string $newNotes
    ): void {
        if (! $entry->daily_entry_id) {
            return;
        }

        $item = DailyEntryItem::query()
            ->where('daily_entry_id', (int) $entry->daily_entry_id)
            ->where('account_id', $fromAccountId)
            ->where('credit', $credit)
            ->orderBy('id')
            ->first();

        if (! $item) {
            $item = DailyEntryItem::query()
                ->where('daily_entry_id', (int) $entry->daily_entry_id)
                ->where('account_id', $fromAccountId)
                ->where('credit', '>', 0)
                ->orderBy('id')
                ->first();
        }

        if (! $item) {
            return;
        }

        $item->account_id = $toAccountId;
        if ($newNotes) {
            $item->notes = $newNotes;
        }
        $item->save();
    }

    private function reduceDailyEntryItemCredit(AccountEntry $entry, int $accountId, float $reduce): void
    {
        if (! $entry->daily_entry_id || $reduce <= 0.009) {
            return;
        }

        $item = DailyEntryItem::query()
            ->where('daily_entry_id', (int) $entry->daily_entry_id)
            ->where('account_id', $accountId)
            ->where('credit', '>=', $reduce)
            ->orderByDesc('credit')
            ->first();

        if (! $item) {
            return;
        }

        $item->credit = round((float) $item->credit - $reduce, 2);
        $item->save();
    }
}
