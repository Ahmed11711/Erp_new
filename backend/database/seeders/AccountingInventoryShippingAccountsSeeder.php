<?php

namespace Database\Seeders;

use App\Models\TreeAccount;
use Illuminate\Database\Seeder;

/**
 * حسابات مطلوبة لفصل المخزون عن شحن التوريد والشحن الصادر وذمم شركات الشحن.
 * يُشغَّل بعد TreeAccountSeeder.
 */
class AccountingInventoryShippingAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $opex = TreeAccount::resolveDefaultOperatingExpenseParent();

        $curLiab = TreeAccount::where('code', '20001')->first()
            ?? TreeAccount::where('type', 'liability')->where('level', 2)->first();

        $salesGrp = TreeAccount::where('code', '40001')->first()
            ?? TreeAccount::where('type', 'revenue')->where('level', 2)->first();

        if ($opex && ! TreeAccount::where('detail_type', 'freight_in')->exists()) {
            TreeAccount::create([
                'name' => 'شحن مشتريات (توريد)',
                'name_en' => 'Purchase freight-in',
                'code' => '500015',
                'parent_id' => $opex->id,
                'type' => 'expense',
                'level' => 3,
                'balance' => 0,
                'debit_balance' => 0,
                'credit_balance' => 0,
                'detail_type' => 'freight_in',
            ]);
        }

        if ($opex && ! TreeAccount::where('detail_type', 'freight_out')->where('name', 'like', '%شحن صادر%')->exists()) {
            TreeAccount::create([
                'name' => 'مصروف شحن صادر (توصيل)',
                'name_en' => 'Outbound freight / delivery',
                'code' => '500016',
                'parent_id' => $opex->id,
                'type' => 'expense',
                'level' => 3,
                'balance' => 0,
                'debit_balance' => 0,
                'credit_balance' => 0,
                'detail_type' => 'freight_out',
            ]);
        }

        if ($curLiab && ! TreeAccount::where('detail_type', 'shipping_courier_payable')->exists()) {
            TreeAccount::create([
                'name' => 'ذمم شركات شحن',
                'name_en' => 'Shipping companies payable',
                'code' => '200013',
                'parent_id' => $curLiab->id,
                'type' => 'liability',
                'level' => 3,
                'balance' => 0,
                'debit_balance' => 0,
                'credit_balance' => 0,
                'detail_type' => 'shipping_courier_payable',
            ]);
        }

        if ($salesGrp && ! TreeAccount::where('detail_type', 'shipping_revenue')->exists()) {
            TreeAccount::create([
                'name' => 'إيراد شحن وتوصيل',
                'name_en' => 'Shipping & handling revenue',
                'code' => '400013',
                'parent_id' => $salesGrp->id,
                'type' => 'revenue',
                'level' => 3,
                'balance' => 0,
                'debit_balance' => 0,
                'credit_balance' => 0,
                'detail_type' => 'shipping_revenue',
            ]);
        }
    }
}
