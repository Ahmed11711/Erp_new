<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\AccountEntry;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DepreciationController extends Controller
{
    public function runDepreciation(Request $request)
    {
        $assets = Asset::where('current_value', '>', DB::raw('COALESCE(scrap_value, 0)'))
                        ->whereNotNull('depreciation_account_id')
                        ->whereNotNull('expense_account_id')
                        ->get();

        $entries = [];
        $errors = [];
        $today = Carbon::today();
        $currentMonth = $today->format('Y-m');
        $accountingService = new AccountingService();

        foreach ($assets as $asset) {
            if ($asset->last_depreciation_date) {
                $lastDepMonth = Carbon::parse($asset->last_depreciation_date)->format('Y-m');
                if ($lastDepMonth === $currentMonth) {
                    continue;
                }
            }

            if ($asset->life_span <= 0) continue;

            $depreciableAmount = $asset->purchase_price - ($asset->scrap_value ?? 0);
            $yearlyDepreciation = $depreciableAmount / $asset->life_span;
            $monthlyDepreciation = $yearlyDepreciation / 12;

            if (($asset->current_value - $monthlyDepreciation) < ($asset->scrap_value ?? 0)) {
                $monthlyDepreciation = $asset->current_value - ($asset->scrap_value ?? 0);
            }

            if ($monthlyDepreciation <= 0) continue;

            try {
                DB::beginTransaction();

                $entry = DailyEntry::create([
                    'date' => $today,
                    'entry_number' => DailyEntry::getNextEntryNumber(),
                    'description' => "Depreciation for Asset: " . $asset->name . " - " . $currentMonth,
                    'user_id' => auth()->id() ?? 1,
                ]);

                DailyEntryItem::create([
                    'daily_entry_id' => $entry->id,
                    'account_id' => $asset->expense_account_id,
                    'debit' => $monthlyDepreciation,
                    'credit' => 0,
                    'notes' => "Depreciation Expense",
                ]);

                DailyEntryItem::create([
                    'daily_entry_id' => $entry->id,
                    'account_id' => $asset->depreciation_account_id,
                    'debit' => 0,
                    'credit' => $monthlyDepreciation,
                    'notes' => "Accumulated Depreciation",
                ]);

                AccountEntry::create([
                    'tree_account_id' => $asset->expense_account_id,
                    'daily_entry_id' => $entry->id,
                    'debit' => $monthlyDepreciation,
                    'credit' => 0,
                    'description' => "Depreciation Expense: " . $asset->name . " - " . $currentMonth,
                ]);

                AccountEntry::create([
                    'tree_account_id' => $asset->depreciation_account_id,
                    'daily_entry_id' => $entry->id,
                    'debit' => 0,
                    'credit' => $monthlyDepreciation,
                    'description' => "Accumulated Depreciation: " . $asset->name . " - " . $currentMonth,
                ]);

                $asset->current_value -= $monthlyDepreciation;
                $asset->last_depreciation_date = $today;
                $asset->save();

                $accountingService->updateAccountHierarchyBalances($asset->expense_account_id);
                $accountingService->updateAccountHierarchyBalances($asset->depreciation_account_id);

                DB::commit();

                $entries[] = "Depreciation of $monthlyDepreciation recorded for {$asset->name}";

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Failed depreciation for asset {$asset->id}: " . $e->getMessage());
                $errors[] = "Failed for {$asset->name}: " . $e->getMessage();
            }
        }

        return response()->json([
            'message' => 'Depreciation run completed',
            'details' => $entries,
            'errors' => $errors,
        ]);
    }
}
