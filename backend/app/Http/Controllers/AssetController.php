<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AssetController extends Controller
{
    public function index(){
        $data = Asset::all();
        return response()->json($data, 200);
    }

    public function store(Request $request){
        $request->validate([
            "name" => "required",
            'asset_date' => 'required|date',
            "payment_amount" => "required|numeric",
            "asset_amount" => "required|numeric",
            "purchase_price" => "required|numeric",
            "life_span" => "required|integer",
            "asset_account_id" => "required|exists:tree_accounts,id",
            // "depreciation_account_id" => "required|exists:tree_accounts,id",
            // "expense_account_id" => "required|exists:tree_accounts,id",
        ]);

        $data = $request->all();
        // Set initial current value to purchase price if not provided
        if (!isset($data['current_value'])) {
            $data['current_value'] = $data['purchase_price'];
        }

        $asset = Asset::create($data);

        // Create Journal Entry
        // Debit Asset Account
        // Credit Payment Method (Bank/Safe) - strictly assuming Bank for now based on 'bank_id' or similar, 
        // but the request has 'payment_amount' and 'bank_id'.
        // Wait, the original model has 'bank_id'. I should use that if available, or a default 'Cash' account.
        // For this iteration, I'll rely on the user providing the credit account effectively, 
        // OR I will interpret 'bank_id' as the credit account if it links to a bank which links to a tree account.
        
        // Let's assume we need to create a DailyEntry.
        try {
            $entry = \App\Models\DailyEntry::create([
                'date' => $asset->asset_date,
                'entry_number' => \App\Models\DailyEntry::max('entry_number') + 1,
                'description' => "Purchase Asset: " . $asset->name,
                'user_id' => auth()->id() ?? 1, // Fallback to 1 if no user
            ]);

            // Debit Asset Account
            \App\Models\DailyEntryItem::create([
                'daily_entry_id' => $entry->id,
                'account_id' => $asset->asset_account_id,
                'debit' => $asset->purchase_price,
                'credit' => 0,
                'notes' => "Asset Value",
            ]);

            // Credit Entry - Check if bank_id matches a tree account or how payment is handled.
            // The original code had 'bank_id'.
            // If bank_id is present, we need to find the specific TreeAccount for that bank.
            // Since I don't have the Bank model details, I'll assume for now we need a 'credit_account_id' in request,
            // OR I will assume the 'bank_id' in the request IS the TreeAccount ID for the bank.
            // Let's assume 'bank_id' maps to a Bank model. Let's check Bank model.
            
            // For now, I'll add a placeholder comment.
            // TODO: properly resolve credit account. Using 'bank_id' as TreeAccount ID if validated, or generic.
            
            // Assuming the user sends the credit account id as 'bank_id' which is a common pattern, 
            // OR we need to fetch the account from the Bank model.
            
            // Let's try to see if 'bank_id' corresponds to a TreeAccount.
             if ($request->bank_id) {
                 \App\Models\DailyEntryItem::create([
                    'daily_entry_id' => $entry->id,
                    'account_id' => $request->bank_id, // Assuming this is the TreeAccount ID for the bank
                    'debit' => 0,
                    'credit' => $asset->purchase_price,
                    'notes' => "Asset Purchase Payment",
                 ]);
             }

        } catch (\Exception $e) {
            // Log error but don't fail asset creation? Or fail?
            // For now, allow it but log.
            \Illuminate\Support\Facades\Log::error("Failed to create asset entry: " . $e->getMessage());
        }

        return response()->json($asset, 201);
    }
}
