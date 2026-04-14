<?php

namespace App\Services\Accounting;

use App\Models\Setting;
use App\Models\TreeAccount;
use App\Models\customerCompany;
use App\Models\Supplier;

/**
 * خدمة مركزية لربط العملاء والموردين بحسابات شجرة الحسابات.
 * تستخدم الإعدادات من صفحة "إعدادات ربط الحسابات" لإنشاء الحسابات الفرعية تلقائياً.
 */
class AccountLinkingService
{
    /**
     * الحصول على الحساب الأب لعملاء الشركات من الإعدادات
     */
    public function getCustomerCorporateParent(): ?TreeAccount
    {
        $parentId = Setting::where('key', 'customer_corporate_parent_account_id')->value('value');
        return $parentId ? TreeAccount::find($parentId) : null;
    }

    /**
     * الحصول على الحساب الأب لعملاء الأفراد من الإعدادات
     */
    public function getCustomerIndividualParent(): ?TreeAccount
    {
        $parentId = Setting::where('key', 'customer_individual_parent_account_id')->value('value');
        return $parentId ? TreeAccount::find($parentId) : null;
    }

    /**
     * الحصول على الحساب الأب للموردين (حسب نوع المورد أو الافتراضي)
     */
    public function getSupplierParent(?int $supplierTypeId = null): ?TreeAccount
    {
        if ($supplierTypeId) {
            $parentId = Setting::where('key', "supplier_type_{$supplierTypeId}_parent_id")->value('value');
            if ($parentId) {
                return TreeAccount::find($parentId);
            }
        }
        $parentId = Setting::where('key', 'supplier_general_parent_id')->value('value');
        return $parentId ? TreeAccount::find($parentId) : null;
    }

    /**
     * إنشاء أو الحصول على حساب شجرة لعميل شركة
     */
    public function ensureCustomerCompanyAccount(customerCompany $company): ?TreeAccount
    {
        if ($company->tree_account_id) {
            return TreeAccount::find($company->tree_account_id);
        }

        $parent = $this->getCustomerCorporateParent();
        if (!$parent) {
            $parent = TreeAccount::where('name', 'like', '%العملاء%')->first();
            if (!$parent) {
                $parent = TreeAccount::firstOrCreate(
                    ['name' => 'العملاء'],
                    ['type' => 'asset', 'balance' => 0, 'code' => 1100, 'level' => 1]
                );
            }
        }

        $account = $this->createChildAccount($parent, $company->name ?? 'عميل ' . $company->id, 'asset');
        $company->tree_account_id = $account->id;
        $company->save();

        return $account;
    }

    /**
     * إنشاء أو الحصول على حساب شجرة لمورد
     */
    public function ensureSupplierAccount(Supplier $supplier): ?TreeAccount
    {
        if ($supplier->tree_account_id) {
            return TreeAccount::find($supplier->tree_account_id);
        }

        $parent = $this->getSupplierParent($supplier->supplier_type);
        if (!$parent) {
            $parent = TreeAccount::where('name', 'like', '%الموردين%')->first();
            if (!$parent) {
                $parent = TreeAccount::firstOrCreate(
                    ['name' => 'الموردين'],
                    ['type' => 'liability', 'balance' => 0, 'code' => 2100, 'level' => 1]
                );
            }
        }

        $account = $this->createChildAccount(
            $parent,
            $supplier->supplier_name ?? 'مورد ' . $supplier->id,
            'liability'
        );
        $supplier->tree_account_id = $account->id;
        $supplier->save();

        return $account;
    }

    /**
     * إنشاء حساب فرعي تحت الحساب الأب
     */
    public function createChildAccount(TreeAccount $parent, string $name, string $type): TreeAccount
    {
        $lastChildCode = TreeAccount::where('parent_id', $parent->id)->max('code');
        $newCode = $lastChildCode ? $lastChildCode + 1 : ($parent->code * 10 + 1);

        return TreeAccount::create([
            'name' => $name,
            'parent_id' => $parent->id,
            'code' => $newCode,
            'type' => $type ?? $parent->type,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
            'level' => $parent->level + 1,
        ]);
    }

    /**
     * إنشاء حساب فرعي تحت الحساب الأب (للاستخدام مع AddAssetController)
     */
    public function createCustomerAccount(string $name, string $type, ?int $parentAccountId = null): ?TreeAccount
    {
        $parent = null;
        if ($parentAccountId) {
            $parent = TreeAccount::find($parentAccountId);
        }

        if (!$parent) {
            $parent = $type === 'شركة'
                ? $this->getCustomerCorporateParent()
                : $this->getCustomerIndividualParent();
        }

        if (!$parent) {
            $parent = $type === 'شركة'
                ? TreeAccount::where('code', 104)->where('level', 3)->first()
                : TreeAccount::where('code', 100025)->first();
        }

        if (!$parent) {
            $parent = TreeAccount::where('name', 'like', '%العملاء%')->first();
            if (!$parent) {
                $parent = TreeAccount::firstOrCreate(
                    ['name' => 'العملاء'],
                    ['type' => 'asset', 'balance' => 0, 'code' => 1100, 'level' => 1]
                );
            }
        }

        return $this->createChildAccount($parent, $name, $parent->type ?? 'asset');
    }

    /**
     * Canonical method to find-or-create an individual customer tree account.
     * ALL code paths MUST use this to avoid duplicate accounts.
     *
     * Canonical name format: "اسم العميل - رقم الموبايل"
     * Search order: canonical name → name only (legacy) → create new with canonical name
     */
    public function ensureIndividualCustomerAccount(string $customerName, ?string $phone): TreeAccount
    {
        $canonicalName = $this->buildCanonicalCustomerName($customerName, $phone);

        // 1. Try canonical name (name + phone)
        $account = TreeAccount::where('name', $canonicalName)->first();
        if ($account) {
            return $account;
        }

        // 2. Try name-only (legacy accounts created before this fix)
        $legacyAccount = TreeAccount::where('name', $customerName)
            ->where('level', 4)
            ->where('type', 'asset')
            ->first();

        if ($legacyAccount) {
            // Rename legacy account to canonical format to prevent future duplicates
            if ($phone && $canonicalName !== $customerName) {
                $legacyAccount->name = $canonicalName;
                $legacyAccount->save();
            }
            return $legacyAccount;
        }

        // 3. Create new account with canonical name
        return $this->createCustomerAccount($canonicalName, 'فرد');
    }

    /**
     * Build the canonical customer account name.
     * Format: "اسم العميل - رقم الموبايل" or just "اسم العميل" if no phone.
     */
    public function buildCanonicalCustomerName(string $customerName, ?string $phone): string
    {
        $phone = trim($phone ?? '');
        if ($phone !== '') {
            return $customerName . ' - ' . $phone;
        }
        return $customerName;
    }

    /**
     * Universal resolver: works for both company and individual customers.
     * Use this from OrderObserver, SalesOrderAccountingService, and OrdersController.
     */
    public function resolveOrderCustomerAccount(
        string $customerType,
        string $customerName,
        ?string $phone,
        ?int $companyId
    ): ?TreeAccount {
        if ($customerType === 'شركة' && $companyId) {
            $company = customerCompany::find($companyId);
            if ($company) {
                return $this->ensureCustomerCompanyAccount($company);
            }
        }

        return $this->ensureIndividualCustomerAccount($customerName, $phone);
    }

    /**
     * يعثر على حساب عميل في الشجرة إن وُجد فقط — لا ينشئ حسابات جديدة (قوائم، تقارير).
     * يُستخدم لعرض مدين/دائن متطابقين مع شجرة الحسابات بعد التحصيل (رصيد صافٍ = 0).
     */
    public function findExistingCustomerTreeAccount(
        string $customerType,
        string $customerName,
        ?string $phone,
        ?int $companyId
    ): ?TreeAccount {
        if ($customerType === 'شركة' && $companyId) {
            $company = customerCompany::find($companyId);
            if ($company && $company->tree_account_id) {
                return TreeAccount::find($company->tree_account_id);
            }

            return null;
        }

        $canonicalName = $this->buildCanonicalCustomerName($customerName, $phone);
        $byCanonical = TreeAccount::where('name', $canonicalName)->first();
        if ($byCanonical) {
            return $byCanonical;
        }

        return TreeAccount::where('name', $customerName)
            ->where('level', 4)
            ->where('type', 'asset')
            ->first();
    }
}
