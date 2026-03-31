<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\TreeAccount;

/**
 * Ensures leaf COGS + inventory accounts exist so ship_order can post cost pairs.
 */
return new class extends Migration
{
    public function up(): void
    {
        $expenseParent = TreeAccount::where('code', '31')->first();
        if ($expenseParent && ! TreeAccount::where('code', '3091001')->exists()) {
            TreeAccount::create([
                'name' => 'تكلفة البضاعة المباعة',
                'name_en' => 'Cost of goods sold',
                'code' => '3091001',
                'type' => 'expense',
                'detail_type' => 'cogs',
                'parent_id' => $expenseParent->id,
                'level' => 4,
            ]);
        }

        $currentAssets = TreeAccount::where('code', '12')->first();
        if ($currentAssets && ! TreeAccount::where('code', '1061001')->exists()) {
            TreeAccount::create([
                'name' => 'مخزون البضاعة',
                'name_en' => 'Inventory',
                'code' => '1061001',
                'type' => 'asset',
                'detail_type' => 'inventory',
                'parent_id' => $currentAssets->id,
                'level' => 4,
            ]);
        }
    }

    public function down(): void
    {
        TreeAccount::where('code', '3091001')->delete();
        TreeAccount::where('code', '1061001')->delete();
    }
};
