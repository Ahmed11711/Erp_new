<?php

namespace App\Console\Commands;

use App\Models\customerCompany;
use App\Services\Accounting\AccountLinkingService;
use Illuminate\Console\Command;

/**
 * ربط عملاء الشركات الذين لا يملكون حساباً في شجرة الحسابات (تحت «عملاء شركات»).
 */
class LinkCustomerCompanyAccountsCommand extends Command
{
    protected $signature = 'accounting:link-customer-companies {--dry-run : عرض النتائج دون إنشاء حسابات}';

    protected $description = 'إنشاء حسابات شجرة لعملاء الشركات غير المربوطين تحت «عملاء شركات»';

    public function handle(AccountLinkingService $linking): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $parent = $linking->getCustomerCorporateParent();

        if (!$parent) {
            $this->error('تعذر العثور على حساب تجميعي «عملاء شركات» في شجرة الحسابات.');

            return self::FAILURE;
        }

        $this->info("الحساب الأب: {$parent->name} ({$parent->code})");

        if ($dryRun) {
            $count = customerCompany::query()->whereNull('tree_account_id')->count();
            customerCompany::query()
                ->whereNull('tree_account_id')
                ->orderBy('id')
                ->each(function ($company) {
                    $this->line("[dry-run] سيتم ربط: {$company->name} (id={$company->id})");
                });
            $this->info("سيتم معالجة {$count} شركة.");

            return self::SUCCESS;
        }

        $result = $linking->linkAllUnlinkedCustomerCompanies();
        if ($result['total'] === 0) {
            $this->info($result['message']);

            return self::SUCCESS;
        }

        foreach ($result['failed_items'] as $item) {
            $this->warn("✗ فشل ربط: {$item['name']} (id={$item['id']})");
        }

        $this->info($result['message']);

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
