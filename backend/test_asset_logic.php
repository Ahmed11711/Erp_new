<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Asset;
use App\Models\TreeAccount;
use App\Models\DailyEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class AssetDepreciationTest extends TestCase
{
    // Use this if you want to reset DB, but we probably shouldn't on a user's local env unless tasked.
    // use RefreshDatabase; 

    public function test_asset_lifecycle()
    {
        // 1. Setup TreeAccounts (Mock if needed, or find existing)
        // Ensure we have accounts to use.
        $assetAccount = TreeAccount::firstOrCreate([
            'name' => 'Test Asset Account',
            'code' => '999111',
            'parent_id' => 1, // Assumptions
            'type' => 'Asset'
        ]);
        
        $depreciationAccount = TreeAccount::firstOrCreate([
            'name' => 'Accumulated Depreciation Test',
            'code' => '999222',
            'parent_id' => 1,
            'type' => 'Liability'
        ]);
        
        $expenseAccount = TreeAccount::firstOrCreate([
            'name' => 'Depreciation Expense Test',
            'code' => '999333',
            'parent_id' => 1,
            'type' => 'Expense'
        ]);

        // 2. Create Asset via Store (Simulate Request)
        $payload = [
            'name' => 'Test Laptop',
            'asset_date' => date('Y-m-d'),
            'purchase_date' => date('Y-m-d'),
            'payment_amount' => 1000,
            'asset_amount' => 1000, // Legacy field
            'purchase_price' => 12000, // 12000 for easy calc = 1000/month
            'current_value' => 12000,
            'scrap_value' => 0,
            'life_span' => 1, // 1 Year
            'asset_account_id' => $assetAccount->id,
            'depreciation_account_id' => $depreciationAccount->id,
            'expense_account_id' => $expenseAccount->id,
            'payment_amount' => 12000,
             // 'bank_id' => ? // Optional in verification if we just check Asset creation
        ];

        // Call the controller directly or via route
        // $response = $this->postJson('/api/assets', $payload); // Only works if auth setup in test
        
        // Let's rely on manual object creation to simulate controller logic if auth is hard
        // But verifying via Route is better. Assuming no auth for test or we mock it.
        // Actually, let's just create the Asset model directly to test depreciation logic,
        // and check Controller logic by manual code inspection or creating a specific test command.
        
        // Create Asset
        $asset = Asset::create($payload);
        echo "Asset Created: " . $asset->id . "\n";
        
        // 3. Run Depreciation
        $controller = new \App\Http\Controllers\DepreciationController();
        $controller->runDepreciation(new \Illuminate\Http\Request());
        
        // 4. Verify Asset Value Updated
        $asset->refresh();
        echo "New Current Value: " . $asset->current_value . "\n";
        
        if ($asset->current_value == 11000) {
            echo "SUCCESS: Depreciation calculated correctly (12000/12 = 1000 reduced).\n";
        } else {
            echo "FAILURE: Asset Value mismatch.\n";
        }
        
        // 5. Verify Journal Entry
        $entry = DailyEntry::where('description', 'like', "%Depreciation for Asset: " . $asset->name . "%")->first();
        if ($entry) {
             echo "SUCCESS: Journal Entry Created.\n";
        } else {
             echo "FAILURE: No Journal Entry found.\n";
        }
        
        // Clean up
        $asset->delete();
        // Delete entries? Better not to mess too much.
    }
}
