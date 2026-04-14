<?php

namespace Database\Seeders;

use App\Models\Stock;
use Illuminate\Database\Seeder;
  
class StockSeeder extends Seeder
{
    public function run(): void
    {
  
    Stock::create(['name' => 'مخزن مواد خام', 'balance' => '254', 'asset_id' => 1,]);
    Stock::create(['name' => 'مخزن منتج تحت التشغيل', 'balance' => '0', 'asset_id' => 1,]);
    Stock::create(['name' => 'مخزن منتج تام', 'balance' => '125', 'asset_id' => 1,]);
    Stock::create(['name' => 'مخزن صيانة', 'balance' => '0', 'asset_id' => 1,]);
    Stock::create(['name' => 'مخزن تالف', 'balance' => '0', 'asset_id' => 1,]);
    }
}
