<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$col = DB::select("SHOW COLUMNS FROM categories WHERE Field = 'category_image'");
echo 'column: '.json_encode($col, JSON_UNESCAPED_UNICODE).PHP_EOL;

$prodId = (int) DB::table('productions')->min('id');
$measId = (int) DB::table('measurements')->min('id');
if ($prodId < 1 || $measId < 1) {
    echo "missing production or measurement\n";
    exit(1);
}

$now = now();
try {
    $id = DB::table('categories')->insertGetId([
        'category_name' => '__shopify_insert_test__',
        'category_price' => 1,
        'unit_price' => 1,
        'total_price' => 1,
        'sell_total_price' => 1,
        'initial_balance' => 0,
        'minimum_quantity' => 0,
        'warehouse' => 'test',
        'production_id' => $prodId,
        'measurement_id' => $measId,
        'category_image' => '',
        'product_type' => 'finished',
        'status' => '1',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    echo "insert ok id={$id}\n";
    DB::table('categories')->where('id', $id)->delete();
} catch (Throwable $e) {
    echo 'insert failed: '.$e->getMessage().PHP_EOL;
    exit(1);
}
