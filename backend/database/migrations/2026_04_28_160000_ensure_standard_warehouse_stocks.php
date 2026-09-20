<?php

use App\Models\Stock;
use App\Models\TreeAccount;
use Illuminate\Database\Migrations\Migration;

/**
 * يضمن وجود صفوف المخازن القياسية في stocks مع ربط asset_id (خصوصًا مخزن تحت التشغيل).
 */
return new class extends Migration
{
    public function up(): void
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

            $stock = Stock::query()->firstOrCreate(
                ['name' => $name],
                ['balance' => 0, 'asset_id' => $assetId]
            );

            if ($stock->asset_id === null || (int) $stock->asset_id === 0) {
                $stock->asset_id = $assetId;
                $stock->save();
            }
        }
    }

    public function down(): void
    {
        //
    }
};
