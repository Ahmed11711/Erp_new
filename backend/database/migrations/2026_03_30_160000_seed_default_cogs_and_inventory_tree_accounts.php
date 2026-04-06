<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\TreeAccount;

/**
 * يضمن وجود حساب COGS + مخزون متوافق مع شجرة TreeAccountSeeder (1000–5000 وأبنائها).
 * - المخزون: ورقة تحت «الأصول المتداولة» كود 10002، عادة كود الحساب 100022 (المخزون).
 * - تكلفة المبيعات: ورقة تحت «مصروفات تشغيلية» كود 50001، كود 500014.
 *
 * الأكواد القديمة 12 / 31 و 1061001 / 3091001 كانت لشجرة قديمة؛ يُنظَّف منها إن وُجدت.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- تنظيف حسابات من migration سابق غير متوافقة مع الشجرة الجديدة ---
        foreach (['3091001', '1061001'] as $legacyCode) {
            TreeAccount::where('code', $legacyCode)->delete();
        }

        $operatingExpenses = TreeAccount::where('code', '50001')->first()
            ?? TreeAccount::where('code', '5000')->first();

        if ($operatingExpenses && ! TreeAccount::where('code', '500014')->exists()) {
            TreeAccount::create([
                'name' => 'تكلفة البضاعة المباعة',
                'name_en' => 'Cost of goods sold',
                'code' => '500014',
                'type' => 'expense',
                'detail_type' => 'cogs',
                'parent_id' => $operatingExpenses->id,
                'level' => $operatingExpenses->code === '5000' ? 2 : 3,
            ]);
        }

        $currentAssets = TreeAccount::where('code', '10002')->first()
            ?? TreeAccount::where('code', '1000')->first();

        if ($currentAssets) {
            $inventoryLeaf = TreeAccount::where('code', '100022')->first();

            if ($inventoryLeaf) {
                $inventoryLeaf->update(array_filter([
                    'detail_type' => 'inventory',
                    'name_en' => $inventoryLeaf->name_en ?: 'Inventory',
                ]));
            } elseif (! TreeAccount::where('code', '100022')->exists()) {
                TreeAccount::create([
                    'name' => 'مخزون البضاعة',
                    'name_en' => 'Inventory',
                    'code' => '100022',
                    'type' => 'asset',
                    'detail_type' => 'inventory',
                    'parent_id' => $currentAssets->id,
                    'level' => $currentAssets->code === '1000' ? 2 : 3,
                ]);
            }
        }
    }

    public function down(): void
    {
        TreeAccount::where('code', '500014')->delete();

        TreeAccount::where('code', '100022')->update(['detail_type' => null]);

        // لا نعيد إنشاء 3091001 / 1061001 — كانت legacy
    }
};
