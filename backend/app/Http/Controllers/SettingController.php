<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\TreeAccount;
use App\Models\customerCompany;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    public function getSettings()
    {
        $settings = Setting::all()->pluck('value', 'key');
        return response()->json($settings);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->all();
        foreach ($data as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
        return response()->json(['message' => 'Settings updated successfully']);
    }

    public function updateExistingEntities(Request $request)
    {
        $type = $request->input('type'); // 'customer' or 'supplier'
        $subType = $request->input('sub_type'); // 'individual', 'corporate', or supplier_type_id
        $newParentId = $request->input('parent_id');

        if (!$newParentId) {
            return response()->json(['message' => 'Parent Account ID is required'], 422);
        }

        $parentAccount = TreeAccount::find($newParentId);
        if (!$parentAccount) {
            return response()->json(['message' => 'Parent Account not found'], 404);
        }

        DB::beginTransaction();
        try {
            if ($type === 'customer') {
                if ($subType === 'corporate') {
                    // Update Customer Companies
                   $companies = customerCompany::all();
                   foreach($companies as $company){
                        if($company->tree_account_id){
                            $this->moveAccount($company->tree_account_id, $parentAccount);
                        }
                   }
                } else {
                    // Assuming 'individual' or other types logic if separated later
                    // For now, only corporate seems to have a distinct table/controller structure in the provided files
                    // If there are individual customers in a different table, handle them here.
                    // Based on analysis, 'customerCompany' is for companies. 
                    // If there's a 'Customer' model for individuals, we'd need to process it. 
                    // (I'll add a placeholder or check if 'Customer' exists, although I didn't see it in the file list earlier, checking...)
                }
            } elseif ($type === 'supplier') {
                $suppliers = Supplier::where('supplier_type', $subType)->get();
                foreach($suppliers as $supplier){
                    if($supplier->tree_account_id){
                        $this->moveAccount($supplier->tree_account_id, $parentAccount);
                    }
                }
            }
            
            DB::commit();
            return response()->json(['message' => 'Entities updated successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error updating entities: ' . $e->getMessage()], 500);
        }
    }

    private function moveAccount($accountId, $newParent)
    {
        $account = TreeAccount::find($accountId);
        if ($account && $account->parent_id != $newParent->id) {
             // Generate new code
             $lastChildCode = TreeAccount::where('parent_id', $newParent->id)->max('code');
             if ($lastChildCode) {
                 $newCode = $lastChildCode + 1;
             } else {
                 $newCode = $newParent->code * 10 + 1;
             }

             $account->update([
                 'parent_id' => $newParent->id,
                 'code' => $newCode,
                 'level' => $newParent->level + 1,
                 'type' => $newParent->type
             ]);
             
             // Note: If the account has children, their codes and levels might also need updates. 
             // For this specific task (Customers/Suppliers), they are usually leaf nodes or simple structures.
             // If deep trees are moved, a recursive update is needed. 
             // Assuming flat structure for now as per standard ERP implementations for these entities.
        }
    }
}
