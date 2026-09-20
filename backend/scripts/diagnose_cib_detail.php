<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$assetId = 850;
$bankId = 11;

echo "=== BANK-DW-80 exact ===\n";
$txn = DB::table('bank_transactions')->where('id', 80)->first();
echo "Transaction: " . json_encode($txn, JSON_UNESCAPED_UNICODE) . "\n";
$entries = DB::table('account_entries')->where('entry_batch_code', 'BANK-DW-80')->get();
foreach ($entries as $e) {
    echo "  Entry #{$e->id}: debit={$e->debit} credit={$e->credit} desc={$e->description}\n";
}
$bds = DB::table('bank_details')->where('bank_id', $bankId)->where('ref', 'BANK-DW-80')->get();
echo "bank_details with ref=BANK-DW-80: " . $bds->count() . "\n";
foreach ($bds as $d) {
    echo "  #{$d->id}: before={$d->balance_before} after={$d->balance_after} amt={$d->amount} type={$d->type}\n";
}
$bds2 = DB::table('bank_details')->where('bank_id', $bankId)->where('ref', '80')->get();
echo "bank_details with ref=80: " . $bds2->count() . "\n";
foreach ($bds2 as $d) {
    echo "  #{$d->id}: before={$d->balance_before} after={$d->balance_after} amt={$d->amount} ref={$d->ref} details={$d->details}\n";
}

echo "\n=== Duplicate refs detail ===\n";
foreach (['BANK-DW-76', 'BANK-DW-83', 'BANK-DW-86'] as $ref) {
    echo "--- {$ref} ---\n";
    $rows = DB::table('bank_details')->where('bank_id', $bankId)->where('ref', $ref)->orderBy('id')->get();
    foreach ($rows as $r) {
        echo "  #{$r->id} {$r->date} before={$r->balance_before} after={$r->balance_after} amt={$r->amount}\n";
    }
}

echo "\n=== Order-linked GL entries ===\n";
$orders = DB::table('account_entries')
    ->where('tree_account_id', $assetId)
    ->whereNotNull('order_id')
    ->orderBy('id')
    ->get(['id', 'order_id', 'debit', 'credit', 'description', 'entry_batch_code', 'created_at']);
$sum = 0;
foreach ($orders as $o) {
    $net = round($o->debit - $o->credit, 2);
    $sum += $net;
    echo "  #{$o->id} order={$o->order_id} net={$net} batch={$o->entry_batch_code} {$o->description}\n";
}
echo "Total order net: {$sum}\n";

echo "\n=== ORD-OPS bank_details ===\n";
$ordOps = DB::table('bank_details')
    ->where('bank_id', $bankId)
    ->where('type', '!=', 'ايداع')
    ->where('type', '!=', 'سحب')
    ->where('details', 'like', '%طلب%')
    ->orWhere('ref', 'like', '%ORD%')
    ->limit(20)
    ->get();
// simpler:
$ordDetails = DB::table('bank_details')->where('bank_id', $bankId)->whereIn('type', ['تحصيل', 'ORD-OPS', 'طلب', 'سندات'])->get();
echo "Special type bank_details: " . $ordDetails->count() . "\n";
foreach ($ordDetails as $d) {
    echo "  #{$d->id} type={$d->type} ref={$d->ref} delta=" . round($d->balance_after - $d->balance_before, 2) . "\n";
}
