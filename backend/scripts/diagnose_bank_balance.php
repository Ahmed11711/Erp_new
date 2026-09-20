<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AccountEntry;
use App\Models\Bank;
use App\Models\TreeAccount;
use App\Services\Accounting\BankOperationalLedgerService;
use Illuminate\Support\Facades\DB;

$bankName = $argv[1] ?? 'CIB';
$bank = Bank::where('name', $bankName)->with('asset')->first();

if (!$bank) {
    echo "Bank not found: {$bankName}\n";
    exit(1);
}

$ledger = app(BankOperationalLedgerService::class);
$assetId = (int) $bank->asset_id;

echo "=== Bank Balance Diagnosis: {$bank->name} ===\n\n";
echo "Bank ID: {$bank->id}\n";
echo "Tree Account ID: {$assetId} (code: {$bank->asset?->code})\n";
echo "banks.balance (operational): " . number_format((float) $bank->balance, 2) . "\n";
echo "GL from account_entries: " . number_format($ledger->glBalanceForBank($bank), 2) . "\n";
echo "tree_accounts.balance (stored): " . number_format((float) ($bank->asset->balance ?? 0), 2) . "\n";

$gap = round((float) $bank->balance - $ledger->glBalanceForBank($bank), 2);
echo "Gap (operational - GL): " . number_format($gap, 2) . "\n\n";

// bank_details reconciliation
$firstDetail = DB::table('bank_details')->where('bank_id', $bank->id)->orderBy('id')->first();
$lastDetail = DB::table('bank_details')->where('bank_id', $bank->id)->orderByDesc('id')->first();
if ($lastDetail) {
    echo "bank_details count: " . DB::table('bank_details')->where('bank_id', $bank->id)->count() . "\n";
    echo "First detail balance_before: " . number_format((float) $firstDetail->balance_before, 2) . "\n";
    echo "Last detail balance_after: " . number_format((float) $lastDetail->balance_after, 2) . "\n";
    $sumAmounts = DB::table('bank_details')
        ->where('bank_id', $bank->id)
        ->selectRaw("SUM(CASE WHEN balance_after > balance_before THEN amount ELSE -amount END) as net")
        ->first();
    echo "Net from bank_details signed amounts: " . number_format((float) ($sumAmounts->net ?? 0), 2) . "\n\n";
}

// Entries without BANK-OPS batch code (likely posted without operational sync)
$opsPrefix = BankOperationalLedgerService::BATCH_PREFIX;
$nonOpsEntries = AccountEntry::query()
    ->where('tree_account_id', $assetId)
    ->where(function ($q) use ($opsPrefix) {
        $q->whereNull('entry_batch_code')
            ->orWhere('entry_batch_code', 'not like', $opsPrefix . '%');
    })
    ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(debit),0) as total_debit, COALESCE(SUM(credit),0) as total_credit')
    ->first();

echo "=== Account entries NOT from BANK-OPS (operational ledger) ===\n";
echo "Count: {$nonOpsEntries->cnt}\n";
echo "Total debit: " . number_format((float) $nonOpsEntries->total_debit, 2) . "\n";
echo "Total credit: " . number_format((float) $nonOpsEntries->total_credit, 2) . "\n";
echo "Net (debit-credit): " . number_format((float) $nonOpsEntries->total_debit - (float) $nonOpsEntries->total_credit, 2) . "\n\n";

// Top non-ops entries by absolute impact
echo "=== Top 20 non-BANK-OPS entries (by |debit-credit|) ===\n";
$topEntries = AccountEntry::query()
    ->where('tree_account_id', $assetId)
    ->where(function ($q) use ($opsPrefix) {
        $q->whereNull('entry_batch_code')
            ->orWhere('entry_batch_code', 'not like', $opsPrefix . '%');
    })
    ->orderByRaw('ABS(debit - credit) DESC')
    ->limit(20)
    ->get(['id', 'debit', 'credit', 'description', 'entry_batch_code', 'voucher_id', 'daily_entry_id', 'order_id', 'created_at']);

foreach ($topEntries as $e) {
    $net = round((float) $e->debit - (float) $e->credit, 2);
    $batch = $e->entry_batch_code ?? '-';
    $desc = mb_substr($e->description ?? '', 0, 60);
    echo sprintf(
        "  #%d | %s | net=%s | batch=%s | voucher=%s | order=%s | %s\n",
        $e->id,
        $e->created_at,
        number_format($net, 2),
        $batch,
        $e->voucher_id ?? '-',
        $e->order_id ?? '-',
        $desc
    );
}

// Entries with no batch (legacy BanksController)
echo "\n=== Legacy entries (no BANK-DW / BANK-OPS batch) ===\n";
$legacy = AccountEntry::query()
    ->where('tree_account_id', $assetId)
    ->where(function ($q) {
        $q->whereNull('entry_batch_code')->orWhere('entry_batch_code', '');
    })
    ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(debit),0)-COALESCE(SUM(credit),0) as net')
    ->first();
echo "Count: {$legacy->cnt}, Net GL impact: " . number_format((float) $legacy->net, 2) . "\n";

$orderEntries = AccountEntry::query()
    ->where('tree_account_id', $assetId)
    ->whereNotNull('order_id')
    ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(debit),0)-COALESCE(SUM(credit),0) as net')
    ->first();
echo "Order-linked count: {$orderEntries->cnt}, Net: " . number_format((float) $orderEntries->net, 2) . "\n";

$bankId = $bank->id;

// Find ALL BANK-DW mismatches
echo "\n=== ALL BANK-DW mismatches ===\n";
$allDw = AccountEntry::query()
    ->where('tree_account_id', $assetId)
    ->where('entry_batch_code', 'like', 'BANK-DW-%')
    ->orderBy('id')
    ->get();

$totalGlDw = 0;
$totalOpsDw = 0;
$allMismatches = [];
foreach ($allDw as $e) {
    $net = round((float) $e->debit - (float) $e->credit, 2);
    $totalGlDw += $net;
    $txnId = (int) str_replace('BANK-DW-', '', $e->entry_batch_code ?? '');
    $bdRows = DB::table('bank_details')
        ->where('bank_id', $bankId)
        ->where(function ($q) use ($txnId, $e) {
            $q->where('ref', (string) $txnId)
                ->orWhere('ref', $e->entry_batch_code)
                ->orWhere('details', 'like', '%' . ($e->entry_batch_code ?? '') . '%');
        })
        ->get();

    $opsDelta = 0;
    foreach ($bdRows as $bd) {
        $opsDelta += round((float) $bd->balance_after - (float) $bd->balance_before, 2);
    }
    $totalOpsDw += $opsDelta;

    if (abs($net - $opsDelta) > 0.02) {
        $allMismatches[] = [
            'batch' => $e->entry_batch_code,
            'gl' => $net,
            'ops' => $opsDelta,
            'bd_count' => $bdRows->count(),
            'diff' => round($net - $opsDelta, 2),
        ];
    }
}
echo "Total GL from BANK-DW: " . number_format($totalGlDw, 2) . "\n";
echo "Total ops from BANK-DW bank_details: " . number_format($totalOpsDw, 2) . "\n";
echo "BANK-DW diff (GL-ops): " . number_format($totalGlDw - $totalOpsDw, 2) . "\n\n";

foreach ($allMismatches as $m) {
    echo sprintf("  %s: GL=%s, ops=%s (rows=%d), diff=%s\n",
        $m['batch'], number_format($m['gl'], 2), number_format($m['ops'], 2), $m['bd_count'], number_format($m['diff'], 2));
}

// Duplicate bank_details refs
echo "\n=== Duplicate bank_details refs ===\n";
$dupes = DB::table('bank_details')
    ->where('bank_id', $bankId)
    ->select('ref', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(balance_after - balance_before) as total_delta'))
    ->groupBy('ref')
    ->having('cnt', '>', 1)
    ->get();
$dupeExtra = 0;
foreach ($dupes as $d) {
    $txn = DB::table('bank_transactions')->where('entry_batch_code', $d->ref)->orWhere('id', (int) preg_replace('/\D/', '', $d->ref))->first();
    $expectedAmt = $txn ? (float) $txn->amount : null;
    $extra = $expectedAmt !== null ? round((float) $d->total_delta - ($txn->type === 'withdrawal' ? -$expectedAmt : $expectedAmt), 2) : (float) $d->total_delta;
    echo sprintf("  ref=%s count=%d total_delta=%s extra_vs_txn=%s\n", $d->ref, $d->cnt, number_format((float) $d->total_delta, 2), $expectedAmt !== null ? number_format($extra, 2) : 'n/a');
    $dupeExtra += $extra;
}
echo "Extra ops from duplicates: " . number_format($dupeExtra, 2) . "\n";

// Reconciliation breakdown
$legacyNet = (float) ($legacy->net ?? 0);
$orderNet = (float) ($orderEntries->net ?? 0);
echo "\n=== Gap breakdown ===\n";
echo "Total gap (ops - GL): " . number_format($gap, 2) . "\n";
echo "  BANK-DW mismatches (GL-ops): " . number_format($totalGlDw - $totalOpsDw, 2) . "\n";
echo "  Legacy entries (GL only): " . number_format($legacyNet, 2) . "\n";
echo "  Order-linked (GL only): " . number_format($orderNet, 2) . "\n";
echo "  Duplicate ops extra: " . number_format($dupeExtra, 2) . "\n";
$explained = ($totalGlDw - $totalOpsDw) + $legacyNet + $orderNet - $dupeExtra;
echo "  Explained GL excess ≈ " . number_format($explained, 2) . " (should ≈ " . number_format(-$gap, 2) . ")\n";


