<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * تصفير قيم المخزون المعروضة في قائمة المخازن (مجموع total_price / sell_total_price للأصناف).
 *
 * الواجهة الأمامية list-warehouse تجلب الرصيد من CategoriesController::warehouse_balance وليس من stocks.balance.
 */
class ResetInventoryWarehouseValuesForTestingCommand extends Command
{
    private const STANDARD_WAREHOUSES = [
        'مخزن مواد خام',
        'مخزن منتج تحت التشغيل',
        'مخزن منتج تام',
        'مخزن صيانة',
        'مخزن تالف',
    ];

    protected $signature = 'erp:reset-inventory-testing
                            {--force : تنفيذ بدون سؤال تأكيد إضافي}
                            {--skip-gl : لا تشغّل تصفير القيود المحاسبية بعد التنفيذ}';

    protected $description = 'تصفير كميات وتقييم الأصناف حسب مخزن + طبقات warehouse_ratings وسجل categories_balance — للاختبار فقط';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->warn('سيُصفَّر مخزون الأصناف والطبقات التاريخية للمخازن المعيارية.');
            if (! $this->confirm('هل تريد المتابعة؟')) {
                return self::SUCCESS;
            }
        }

        DB::transaction(function () {
            $placeholders = implode(',', array_fill(0, count(self::STANDARD_WAREHOUSES), '?'));

            $categoryIds = DB::table('categories')
                ->whereIn('warehouse', self::STANDARD_WAREHOUSES)
                ->pluck('id');

            if ($categoryIds->isNotEmpty()) {
                DB::table('warehouse_ratings')->whereIn('category_id', $categoryIds)->delete();
                DB::table('categories_balance')->whereIn('category_id', $categoryIds)->delete();
            }

            DB::update(
                "
                UPDATE categories
                SET
                    quantity = 0,
                    total_price = 0,
                    unit_price = 0,
                    sell_total_price = 0,
                    updated_at = NOW()
                WHERE warehouse IN ($placeholders)
                ",
                self::STANDARD_WAREHOUSES
            );

            DB::table('stocks')->update([
                'balance' => 0,
                'updated_at' => now(),
            ]);
        });

        $this->info('تم تصفير الأصناف للمخازن المعيارية وحذف warehouse_ratings و categories_balance المرتبطة، وتصفير stocks.balance.');

        if (! $this->option('skip-gl')) {
            $this->call('erp:reset-gl-testing', ['--force' => true, '--yes' => true]);
        }

        return self::SUCCESS;
    }
}
