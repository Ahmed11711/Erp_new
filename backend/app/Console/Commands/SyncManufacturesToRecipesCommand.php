<?php

namespace App\Console\Commands;

use App\Models\Manufacture;
use App\Models\Recipe;
use App\Services\Manufacturing\ManufactureRecipeSyncService;
use Illuminate\Console\Command;

/**
 * لمرة واحدة أو بعد الترقية: إنشاء سجلات Recipe للوصفات المحفوظة يدويًا قبل ربط Manufacture بـ Recipe.
 */
class SyncManufacturesToRecipesCommand extends Command
{
    protected $signature = 'recipes:sync-from-manufactures {--force : إعادة مزامنة حتى لو وُجد Recipe لنفس المنتج}';

    protected $description = 'إنشاء/تحديث وصفات Recipe من جدول manufactures الموجود';

    public function handle(ManufactureRecipeSyncService $syncService): int
    {
        $force = (bool) $this->option('force');
        $synced = 0;
        $skipped = 0;

        Manufacture::query()->with('manufacture_products')->orderBy('id')->chunk(100, function ($rows) use ($syncService, $force, &$synced, &$skipped) {
            foreach ($rows as $m) {
                $pid = (int) $m->product_id;
                if (! $force && Recipe::query()->where('output_item_id', $pid)->exists()) {
                    $skipped++;

                    continue;
                }

                $products = [];
                foreach ($m->manufacture_products as $mp) {
                    $products[] = [
                        'id' => $mp->product_id,
                        'quantity' => $mp->quantity,
                        'total_price' => $mp->total_price,
                    ];
                }
                if (count($products) === 0) {
                    $this->warn("تخطّي manufacture #{$m->id}: لا توجد مواد.");
                    $skipped++;

                    continue;
                }

                try {
                    $syncService->sync($pid, $products, []);
                    $synced++;
                    $this->line("تمت المزامنة للمنتج #{$pid}");
                } catch (\InvalidArgumentException $e) {
                    $this->warn("تخطّي المنتج #{$pid}: {$e->getMessage()}");
                    $skipped++;
                }
            }
        });

        $this->info("اكتمل: مزامنة {$synced}، تخطّي {$skipped}.");

        return self::SUCCESS;
    }
}
