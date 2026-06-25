<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Services\Items\CategoryMergeService;
use Illuminate\Console\Command;

class MergeDuplicateCategoriesCommand extends Command
{
    protected $signature = 'categories:merge-duplicates
                            {--stock-id= : Limit to a specific warehouse stock id}
                            {--dry-run : Show duplicate groups without merging}
                            {--force : Merge without confirmation prompt}';

    protected $description = 'Find duplicate item names in the same warehouse and merge them into one canonical row';

    public function handle(CategoryMergeService $mergeService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $stockId = $this->option('stock-id') !== null ? (int) $this->option('stock-id') : null;

        $this->info($dryRun ? '=== DRY RUN — no changes ===' : '=== Merging duplicate categories ===');
        $this->newLine();

        $groups = $this->findDuplicateGroups($stockId);
        if ($groups === []) {
            $this->info('No duplicate category names found in the same warehouse.');

            return Command::SUCCESS;
        }

        $merged = 0;
        foreach ($groups as $group) {
            $canonical = $this->pickCanonical($group);
            $duplicates = array_values(array_filter($group, fn (Category $c) => (int) $c->id !== (int) $canonical->id));

            $this->warn("Duplicate group in «{$canonical->warehouse}»: «{$canonical->category_name}»");
            $this->line("  Keep:   #{$canonical->id} [{$canonical->item_code}] qty={$canonical->quantity}");

            foreach ($duplicates as $dup) {
                $links = $mergeService->countLinks((int) $dup->id);
                $linkSummary = $links === [] ? 'no links' : collect($links)->map(fn ($n, $k) => "{$k}:{$n}")->implode(', ');
                $this->line("  Merge:  #{$dup->id} [{$dup->item_code}] qty={$dup->quantity} ({$linkSummary})");

                if ($dryRun) {
                    continue;
                }

                if (! $this->option('force') && ! $this->confirm("Merge #{$dup->id} into #{$canonical->id}?", true)) {
                    continue;
                }

                try {
                    $mergeService->merge((int) $dup->id, (int) $canonical->id);
                    $this->info("  → Merged #{$dup->id} into #{$canonical->id}");
                    $merged++;
                    $canonical->refresh();
                } catch (\Throwable $e) {
                    $this->error("  → Failed: {$e->getMessage()}");
                }
            }

            $this->newLine();
        }

        if ($dryRun) {
            $this->info('Found '.count($groups).' duplicate group(s). Run without --dry-run to merge.');
        } else {
            $this->info("Done. Merged {$merged} duplicate category row(s).");
        }

        return Command::SUCCESS;
    }

    /**
     * @return list<list<Category>>
     */
    private function findDuplicateGroups(?int $stockId): array
    {
        $query = Category::query()->orderBy('id');
        if ($stockId !== null && $stockId > 0) {
            $query->where('stock_id', $stockId);
        }

        $byKey = [];
        foreach ($query->get() as $category) {
            $key = ((int) ($category->stock_id ?? 0)).'|'.$this->normalizeName((string) $category->category_name);
            $byKey[$key][] = $category;
        }

        return array_values(array_filter($byKey, fn (array $group) => count($group) > 1));
    }

    private function normalizeName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return mb_strtolower($name);
    }

    /**
     * Prefer the row with item_code, then highest quantity, then lowest id.
     *
     * @param  list<Category>  $group
     */
    private function pickCanonical(array $group): Category
    {
        usort($group, function (Category $a, Category $b) {
            $aCode = $a->item_code ? 1 : 0;
            $bCode = $b->item_code ? 1 : 0;
            if ($aCode !== $bCode) {
                return $bCode <=> $aCode;
            }

            $qtyCmp = ((float) ($b->quantity ?? 0)) <=> ((float) ($a->quantity ?? 0));
            if ($qtyCmp !== 0) {
                return $qtyCmp;
            }

            return ((int) $a->id) <=> ((int) $b->id);
        });

        return $group[0];
    }
}
