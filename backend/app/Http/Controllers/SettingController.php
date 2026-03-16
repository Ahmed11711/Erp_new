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
        $subType = $request->input('sub_type'); // 'individual', 'corporate', 'general', or supplier_type_id
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
                    $companies = customerCompany::all();
                    foreach ($companies as $company) {
                        if ($company->tree_account_id) {
                            $this->moveAccount($company->tree_account_id, $parentAccount);
                        } else {
                            // إنشاء حساب جديد للعميل تحت الحساب الأب المحدث
                            $account = $this->createChildAccount($parentAccount, $company->name ?? 'عميل ' . $company->id, $parentAccount->type ?? 'asset');
                            $company->tree_account_id = $account->id;
                            $company->save();
                        }
                    }
                }
            } elseif ($type === 'supplier') {
                $suppliers = $this->getSuppliersForUpdate($subType);
                foreach ($suppliers as $supplier) {
                    if ($supplier->tree_account_id) {
                        $this->moveAccount($supplier->tree_account_id, $parentAccount);
                    } else {
                        // إنشاء حساب جديد للمورد تحت الحساب الأب المحدث
                        $account = $this->createChildAccount($parentAccount, $supplier->supplier_name ?? 'مورد ' . $supplier->id, $parentAccount->type ?? 'liability');
                        $supplier->tree_account_id = $account->id;
                        $supplier->save();
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

    /**
     * الحصول على الموردين المراد تحديثهم حسب النوع
     * sub_type = 'general' => الموردين الذين يستخدمون الحساب الافتراضي (بدون نوع أو نوعهم بدون إعداد خاص)
     * sub_type = supplier_type_id => الموردين من نوع معين
     */
    private function getSuppliersForUpdate($subType)
    {
        if ($subType === 'general') {
            $supplierTypeIdsWithSetting = Setting::where('key', 'like', 'supplier_type_%_parent_id')
                ->whereNotNull('value')
                ->where('value', '!=', '')
                ->pluck('key')
                ->map(fn ($key) => (int) preg_replace('/supplier_type_(\d+)_parent_id/', '$1', $key))
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();
            return Supplier::when(count($supplierTypeIdsWithSetting) > 0, function ($q) use ($supplierTypeIdsWithSetting) {
                $q->where(function ($sub) use ($supplierTypeIdsWithSetting) {
                    $sub->whereNull('supplier_type')
                        ->orWhereNotIn('supplier_type', $supplierTypeIdsWithSetting);
                });
            })->get();
        }
        return Supplier::where('supplier_type', $subType)->get();
    }

    /**
     * إنشاء حساب فرعي تحت الحساب الأب
     */
    private function createChildAccount(TreeAccount $parent, string $name, string $type): TreeAccount
    {
        $lastChildCode = TreeAccount::where('parent_id', $parent->id)->max('code');
        $newCode = $lastChildCode ? $lastChildCode + 1 : ($parent->code * 10 + 1);

        return TreeAccount::create([
            'name' => $name,
            'parent_id' => $parent->id,
            'code' => $newCode,
            'type' => $type,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
            'level' => $parent->level + 1,
        ]);
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
