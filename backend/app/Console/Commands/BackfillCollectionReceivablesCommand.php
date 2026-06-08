<?php

namespace App\Console\Commands;

use App\Enums\CollectionProviderType;
use App\Enums\OrderCollectionStatus;
use App\Enums\OrderSettlementStatus;
use App\Models\OrderDetails;
use Illuminate\Console\Command;

/**
 * تعبئة ذمة التحصيل (collection_receivable_amount) للطلبات المدفوعة إلكترونياً
 * (Shopify: Paymob / valU / Sympl / Visa …) التي رُبطت بشركة تحصيل لكن لم تُسجَّل
 * مديونيتها التشغيلية بعد — حتى تظهر فوراً في تقرير «ذمم شركات التحصيل والدفع».
 *
 * القيود المحاسبية (GL) تُسجَّل وقت الاستيراد؛ هذا الأمر يصلح اللقطة التشغيلية فقط.
 */
class BackfillCollectionReceivablesCommand extends Command
{
    protected $signature = 'collection:backfill-receivables {--dry-run : عرض دون تعديل}';

    protected $description = 'تعبئة ذمة التحصيل للطلبات المدفوعة إلكترونياً المرتبطة بشركة تحصيل';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = OrderDetails::query()
            ->where('collection_provider_type', CollectionProviderType::CollectionCompany->value)
            ->whereNotNull('collection_provider_id')
            ->where(function ($q) {
                $q->whereNull('collection_receivable_amount')
                    ->orWhere('collection_receivable_amount', '<=', 0.009);
            })
            ->whereHas('order', fn ($o) => $o->where('prepaid_amount', '>', 0.009));

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('لا توجد طلبات بحاجة لتعبئة ذمة التحصيل.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[dry-run] ' : '') . "سيتم معالجة {$total} طلب.");

        $updated = 0;
        $query->with('order:id,prepaid_amount,order_status')
            ->chunkById(200, function ($rows) use (&$updated, $dryRun) {
                foreach ($rows as $od) {
                    $prepaid = round((float) ($od->order->prepaid_amount ?? 0), 3);
                    if ($prepaid <= 0.009) {
                        continue;
                    }

                    $this->line("طلب #{$od->order_id}: ذمة تحصيل = {$prepaid}");

                    if ($dryRun) {
                        $updated++;
                        continue;
                    }

                    $od->collection_receivable_amount = $prepaid;
                    if (! in_array($od->collection_status, [
                        OrderCollectionStatus::Collected->value,
                        OrderCollectionStatus::Transferred->value,
                        OrderCollectionStatus::Refused->value,
                        OrderCollectionStatus::Partial->value,
                    ], true)) {
                        $od->collection_status = OrderCollectionStatus::Pending->value;
                    }
                    if (! $od->settlement_status || $od->settlement_status === OrderSettlementStatus::NotApplicable->value) {
                        $od->settlement_status = OrderSettlementStatus::Open->value;
                    }
                    $od->saveQuietly();
                    $updated++;
                }
            });

        $this->info(($dryRun ? '[dry-run] ' : '') . "تمت معالجة {$updated} طلب.");

        return self::SUCCESS;
    }
}
