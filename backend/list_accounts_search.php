<?php
// list_accounts_search.php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\TreeAccount;

$keywords = ['مبيعات', 'مشتريات', 'مخزون', 'عملاء', 'موردين', 'Sales', 'Purchases', 'Inventory', 'Customers', 'Suppliers'];

foreach ($keywords as $keyword) {
    echo "--- Searching for: $keyword ---\n";
    $accounts = TreeAccount::where('name', 'like', "%$keyword%")->get(['id', 'name', 'code', 'type']);
    foreach ($accounts as $account) {
        echo "ID: {$account->id} | Code: {$account->code} | Name: {$account->name} | Type: {$account->type}\n";
    }
    echo "\n";
}
