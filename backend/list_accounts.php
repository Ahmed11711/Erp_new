<?php
// list_accounts.php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\TreeAccount;

$accounts = TreeAccount::all(['id', 'name', 'code', 'type']);
foreach ($accounts as $account) {
    echo "ID: {$account->id} | Code: {$account->code} | Name: {$account->name} | Type: {$account->type}\n";
}
