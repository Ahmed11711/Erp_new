<?php

namespace Database\Seeders;

use App\Models\Stock;
use App\Models\TreeAccount;
use Illuminate\Database\Seeder;

class StockSeeder extends Seeder
{
    /**
     * يربط كل مخزن بالحساب الفرعي المناسب تحت «المخزون» (مثال: 1000221–1000223) عند توفره في شجرة الحسابات.
     */
    public function run(): void
    {
        $byName = [
            'مخزن مواد خام' => ['1000223', '100022'],
            'مخزن منتج تحت التشغيل' => ['1000222', '100022'],
            'مخزن منتج تام' => ['1000221', '100022'],
            'مخزن صيانة' => ['1000221', '100022'],
            'مخزن تالف' => ['1000221', '100022'],
        ];

        foreach ($byName as $name => $codes) {
            $assetId = null;
            foreach ($codes as $code) {
                $tid = TreeAccount::where('code', (string) $code)->value('id');
                if ($tid) {
                    $assetId = (int) $tid;
                    break;
                }
            }
            if ($assetId === null) {
                $fallback = TreeAccount::resolveInventoryAccount();

                $assetId = $fallback ? (int) $fallback->id : 1;
            }

            Stock::query()->firstOrCreate(
                ['name' => $name],
                ['balance' => 0, 'asset_id' => $assetId]
            );
        }
    }
}
