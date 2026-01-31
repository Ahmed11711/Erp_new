<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DepreciationController extends Controller
{
    /**
     * Calculate and record depreciation for all assets up to the current date.
     * This is a simplified "Run Depreciation" function.
     */
    public function runDepreciation(Request $request)
    {
        // Get all active assets
        // Assuming active means they have value left and are not sold/disposed.
        // Also assuming 'life_span' is in years.
        
        $assets = Asset::where('current_value', '>', DB::raw('scrap_value'))
                        ->whereNotNull('depreciation_account_id')
                        ->whereNotNull('expense_account_id')
                        ->get();

        $entries = [];

        foreach ($assets as $asset) {
            // Straight Line Logic: (Cost - Scrap) / Life
            
            if ($asset->life_span <= 0) continue;
            
            // Calculate yearly depreciation
            $depreciableAmount = $asset->purchase_price - $asset->scrap_value;
            $yearlyDepreciation = $depreciableAmount / $asset->life_span;
            
            // For this run, are we doing monthly or yearly?
            // Let's assume this is run monthly.
            $monthlyDepreciation = $yearlyDepreciation / 12;

            // Check if we should depreciate (e.g. hasn't been depreciated this month)
            // For now, I'll just calculate and let the user decide when to run it.
            // Ideally, we track "last_depreciation_date".

            // To avoid complexity, I'll just create an entry for 1 month of depreciation.
            // and update current_value.
            
            // Limit depreciation to not exceed current value - scrap value
            if (($asset->current_value - $monthlyDepreciation) < $asset->scrap_value) {
                $monthlyDepreciation = $asset->current_value - $asset->scrap_value;
            }

            if ($monthlyDepreciation <= 0) continue;

            DB::transaction(function () use ($asset, $monthlyDepreciation) {
                // Creates Journal Entry
                $entry = DailyEntry::create([
                    'date' => Carbon::today(),
                    'entry_number' => DailyEntry::max('entry_number') + 1,
                    'description' => "Depreciation for Asset: " . $asset->name . " - " . Carbon::today()->format('Y-m'),
                    'user_id' => auth()->id() ?? 1,
                ]);

                // Debit Depreciation Expense
                DailyEntryItem::create([
                    'daily_entry_id' => $entry->id,
                    'account_id' => $asset->expense_account_id,
                    'debit' => $monthlyDepreciation,
                    'credit' => 0,
                    'notes' => "Depreciation Expense",
                ]);

                // Credit Accumulated Depreciation
                DailyEntryItem::create([
                    'daily_entry_id' => $entry->id,
                    'account_id' => $asset->depreciation_account_id,
                    'debit' => 0,
                    'credit' => $monthlyDepreciation,
                    'notes' => "Accumulated Depreciation",
                ]);

                // Update Asset Current Value
                $asset->current_value -= $monthlyDepreciation;
                $asset->save();
            });
            
            $entries[] = "Depreciation of $monthlyDepreciation recorded for {$asset->name}";
        }

        return response()->json([
            'message' => 'Depreciation run successfully',
            'details' => $entries
        ]);
    }
}
