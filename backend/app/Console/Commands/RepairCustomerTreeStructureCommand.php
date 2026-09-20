<?php

namespace App\Console\Commands;

use App\Models\AccountEntry;
use App\Models\Setting;
use App\Models\TreeAccount;
use App\Services\Accounting\AccountLinkingService;
use App\Services\Accounting\TreeAccountBalanceRebuildService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * إصلاح شجرة عملاء الذمم:
 * - نقل عملاء القنوات الإلكترونية تحت Shopify ← عملاء أفراد ← العملاء
 * - حذف حسابات التجميع المكررة «عملاء أونلاين» (مثل 1000236 و 1000246)
 * - ضبط إعدادات الربط المحاسبي
 */
class RepairCustomerTreeStructureCommand extends Command
{
    protected $signature = 'accounting:repair-customer-tree
        {--dry-run : معاينة دون تنفيذ}
        {--skip-settings : عدم تحديث جدول settings}';

    protected $description = 'إصلاح تكرار عملاء أونلاين ونقلهم تحت Shopify ضمن عملاء أفراد';

    private const LEGACY_ONLINE_BUCKET_NAMES = [
        'عملاء أونلاين',
        'عملاء اونلاين',
    ];

    public function handle(AccountLinkingService $linking, TreeAccountBalanceRebuildService $balanceRebuild): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun ? '=== معاينة إصلاح شجرة العملاء ===' : '=== إصلاح شجرة العملاء ===');
        $this->newLine();

        $individualParent = $linking->getCustomerIndividualParent();
        if (!$individualParent) {
            $this->error('تعذر العثور على حساب «عملاء أفراد».');

            return self::FAILURE;
        }

        $this->line("عملاء أفراد: [{$individualParent->code}] {$individualParent->name} (id={$individualParent->id})");

        $shopifyParent = $dryRun
            ? TreeAccount::query()
                ->where('parent_id', $individualParent->id)
                ->where(function ($q) {
                    $q->where('name', 'Shopify')->orWhere('name', 'شوبيفاي');
                })
                ->first()
            : $linking->ensureShopifyBucket();

        if (!$shopifyParent) {
            if ($dryRun) {
                $this->line('Shopify: سيُنشأ حساب جديد تحت «عملاء أفراد».');
            } else {
                $this->error('تعذر إنشاء/جلب حساب Shopify.');

                return self::FAILURE;
            }
        } else {
            $this->line("Shopify: [{$shopifyParent->code}] {$shopifyParent->name} (id={$shopifyParent->id})");
        }
        $this->newLine();

        $legacyBuckets = TreeAccount::query()
            ->where('type', 'asset')
            ->whereIn('name', self::LEGACY_ONLINE_BUCKET_NAMES)
            ->orderBy('id')
            ->get();

        if ($legacyBuckets->isEmpty()) {
            $this->info('لا توجد حسابات تجميعية قديمة «عملاء أونلاين».');
        }

        $moved = 0;
        $merged = 0;
        $deletedBuckets = 0;

        if (!$dryRun) {
            DB::transaction(function () use (
                $individualParent,
                $shopifyParent,
                $legacyBuckets,
                $balanceRebuild,
                &$moved,
                &$merged,
                &$deletedBuckets
            ) {
                foreach ($legacyBuckets as $bucket) {
                    $this->relocateBucketDescendants($bucket, $shopifyParent, $balanceRebuild, $moved, $merged);
                }

                foreach ($legacyBuckets->fresh() as $bucket) {
                    if ($this->canDeleteBucket($bucket)) {
                        $this->warn("حذف حساب تجميعي فارغ: [{$bucket->code}] {$bucket->name}");
                        $bucket->forceDelete();
                        $deletedBuckets++;
                    }
                }

                if (!$this->option('skip-settings')) {
                    $this->persistSettings($individualParent, $shopifyParent);
                }

                // تجميع أرصدة فرع العملاء فقط — لا تُعاد حسابات الشجرة بالكامل
                $balanceRebuild->rebuildAncestorChain($shopifyParent->id);
            });
        } else {
            foreach ($legacyBuckets as $bucket) {
                $parent = TreeAccount::find($bucket->parent_id);
                $childCount = TreeAccount::where('parent_id', $bucket->id)->count();
                $this->line("سيُفرَّغ: [{$bucket->code}] {$bucket->name} تحت «" . ($parent?->name ?? '?') . "» — {$childCount} حساب فرعي");
                $moved += $childCount;
            }
            $deletedBuckets = $legacyBuckets->count();
        }

        $this->newLine();
        $this->info($dryRun
            ? "معاينة: نقل ~{$moved} حساب، حذف ~{$deletedBuckets} حساب تجميعي."
            : "تم: نقل {$moved} حساب، دمج {$merged} مكرر، حذف {$deletedBuckets} حساب تجميعي.");

        return self::SUCCESS;
    }

    private function relocateBucketDescendants(
        TreeAccount $bucket,
        TreeAccount $shopifyParent,
        TreeAccountBalanceRebuildService $balanceRebuild,
        int &$moved,
        int &$merged
    ): void {
        $children = TreeAccount::where('parent_id', $bucket->id)->orderBy('id')->get();

        foreach ($children as $child) {
            if ($this->isLegacyOnlineBucket($child)) {
                $this->relocateBucketDescendants($child, $shopifyParent, $balanceRebuild, $moved, $merged);
                if ($this->canDeleteBucket($child->fresh())) {
                    $child->forceDelete();
                }

                continue;
            }

            if (TreeAccount::where('parent_id', $child->id)->exists()) {
                $this->relocateBucketDescendants($child, $shopifyParent, $balanceRebuild, $moved, $merged);
                if ($this->canDeleteBucket($child->fresh())) {
                    $child->forceDelete();
                }

                continue;
            }

            if ($this->moveOrMergeLeafAccount($child, $shopifyParent, $balanceRebuild)) {
                $merged++;
            } else {
                $moved++;
            }
        }
    }

    private function moveOrMergeLeafAccount(
        TreeAccount $account,
        TreeAccount $newParent,
        TreeAccountBalanceRebuildService $balanceRebuild
    ): bool {
        $duplicate = TreeAccount::query()
            ->where('parent_id', $newParent->id)
            ->where('name', $account->name)
            ->where('id', '!=', $account->id)
            ->first();

        if ($duplicate) {
            $this->line("  دمج: \"{$account->name}\" [{$account->code}] → [{$duplicate->code}]");

            AccountEntry::where('tree_account_id', $account->id)
                ->update(['tree_account_id' => $duplicate->id]);

            DB::table('daily_entry_items')
                ->where('account_id', $account->id)
                ->update(['account_id' => $duplicate->id]);

            $balanceRebuild->applyBalanceFromEntries($duplicate->fresh());
            $account->forceDelete();

            return true;
        }

        $this->line("  نقل: [{$account->code}] {$account->name}");

        $account->update([
            'parent_id' => $newParent->id,
            'level' => $newParent->level + 1,
            'type' => $newParent->type ?? 'asset',
        ]);

        return false;
    }

    private function isLegacyOnlineBucket(TreeAccount $account): bool
    {
        return in_array(trim($account->name), self::LEGACY_ONLINE_BUCKET_NAMES, true);
    }

    private function canDeleteBucket(TreeAccount $bucket): bool
    {
        if (! $this->isLegacyOnlineBucket($bucket)) {
            return false;
        }

        if (TreeAccount::where('parent_id', $bucket->id)->exists()) {
            return false;
        }

        return AccountEntry::where('tree_account_id', $bucket->id)->count() === 0;
    }

    private function persistSettings(TreeAccount $individualParent, TreeAccount $shopifyParent): void
    {
        Setting::updateOrCreate(
            ['key' => 'customer_individual_parent_account_id'],
            ['value' => (string) $individualParent->id]
        );
        Setting::updateOrCreate(
            ['key' => 'customer_online_parent_account_id'],
            ['value' => (string) $shopifyParent->id]
        );

        $corporate = TreeAccount::query()
            ->where('type', 'asset')
            ->where('name', 'عملاء شركات')
            ->orderByRaw("CASE WHEN code = '1000235' OR code = 1000235 THEN 0 ELSE 1 END")
            ->first();

        if ($corporate) {
            Setting::updateOrCreate(
                ['key' => 'customer_corporate_parent_account_id'],
                ['value' => (string) $corporate->id]
            );
        }

        $this->info('تم تحديث إعدادات الربط المحاسبي (customer_*_parent_account_id).');
    }
}
