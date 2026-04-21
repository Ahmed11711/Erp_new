<?php

namespace App\Services\Accounting;

use App\Models\OrderSource;
use App\Models\Setting;
use App\Models\TreeAccount;
use App\Models\customerCompany;
use App\Models\Supplier;
use Illuminate\Support\Facades\Log;

/**
 * خدمة مركزية لربط العملاء والموردين بحسابات شجرة الحسابات.
 * تستخدم الإعدادات من صفحة "إعدادات ربط الحسابات" لإنشاء الحسابات الفرعية تلقائياً.
 *
 * يجب أن تُنشأ الحسابات دائماً تحت فروع الأصول/الخصوم (مثل المدينون تحت الأصول المتداولة)،
 * ولا يُنشأ حساب جذر باسم "العملاء" أو "الموردين" لأنه يكسر التسلسل الهرمي في الواجهة.
 */
class AccountLinkingService
{
    private const BUCKET_INDIVIDUALS = 'عملاء أفراد';

    private const BUCKET_CORPORATE = 'عملاء شركات';

    private const BUCKET_ONLINE = 'عملاء أونلاين';

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
     * الحساب الأب لعملاء القنوات الإلكترونية (Shopify وغيرها) — اختياري في الإعدادات
     */
    public function getCustomerOnlineParent(): ?TreeAccount
    {
        $parentId = Setting::where('key', 'customer_online_parent_account_id')->value('value');

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

        $parent = $this->getCustomerCorporateParent()
            ?? $this->ensureReceivableBucket(self::BUCKET_CORPORATE);

        if (!$parent) {
            Log::error('AccountLinkingService: cannot resolve parent for corporate customer tree account', [
                'company_id' => $company->id,
            ]);

            return null;
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

        $parent = $this->getSupplierParent($supplier->supplier_type)
            ?? $this->resolveDefaultSupplierParentAccount();

        if (!$parent) {
            Log::error('AccountLinkingService: cannot resolve parent for supplier tree account', [
                'supplier_id' => $supplier->id,
            ]);

            return null;
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

        $kind = $this->normalizeCustomerKind($type);

        if (!$parent) {
            $parent = match ($kind) {
                'corporate' => $this->getCustomerCorporateParent()
                    ?? $this->ensureReceivableBucket(self::BUCKET_CORPORATE),
                'online' => $this->getCustomerOnlineParent()
                    ?? $this->ensureReceivableBucket(self::BUCKET_ONLINE),
                default => $this->getCustomerIndividualParent()
                    ?? $this->ensureReceivableBucket(self::BUCKET_INDIVIDUALS),
            };
        }

        if (!$parent) {
            $parent = $this->fallbackCustomerParentByLegacyCodes($kind);
        }

        if (!$parent) {
            Log::error('AccountLinkingService: createCustomerAccount could not resolve any parent', [
                'name' => $name,
                'type' => $type,
            ]);

            return null;
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
        return $this->ensureNamedReceivableCustomerAccount($customerName, $phone, 'individual');
    }

    /**
     * عميل فردي من قناة إلكترونية — تحت «عملاء أونلاين» (أو الإعداد المخصص).
     */
    public function ensureOnlineChannelCustomerAccount(string $customerName, ?string $phone): TreeAccount
    {
        return $this->ensureNamedReceivableCustomerAccount($customerName, $phone, 'online');
    }

    /**
     * @param 'individual'|'online' $channel
     */
    private function ensureNamedReceivableCustomerAccount(string $customerName, ?string $phone, string $channel): TreeAccount
    {
        $canonicalName = $this->buildCanonicalCustomerName($customerName, $phone);

        $account = TreeAccount::where('name', $canonicalName)->first();
        if ($account) {
            return $account;
        }

        $legacyAccount = TreeAccount::where('name', $customerName)
            ->where('type', 'asset')
            ->first();

        if ($legacyAccount) {
            if ($phone && $canonicalName !== $customerName) {
                $legacyAccount->name = $canonicalName;
                $legacyAccount->save();
            }

            return $legacyAccount;
        }

        $typeLabel = $channel === 'online' ? 'أونلاين' : 'فرد';

        $created = $this->createCustomerAccount($canonicalName, $typeLabel);
        if (!$created) {
            throw new \RuntimeException('AccountLinkingService: failed to create customer tree account for ' . $canonicalName);
        }

        return $created;
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
     *
     * @param  ?int  $orderSourceId  عند تمريره يُستخدم لاحتساب قناة «أونلاين» تلقائياً (Shopify، ويب، …)
     */
    public function resolveOrderCustomerAccount(
        string $customerType,
        string $customerName,
        ?string $phone,
        ?int $companyId,
        ?int $orderSourceId = null
    ): ?TreeAccount {
        if ($this->isCompanyCustomerType($customerType) && $companyId) {
            $company = customerCompany::find($companyId);
            if ($company) {
                return $this->ensureCustomerCompanyAccount($company);
            }
        }

        if ($this->normalizeCustomerKind($customerType) === 'online') {
            return $this->ensureOnlineChannelCustomerAccount($customerName, $phone);
        }

        if ($this->shouldUseOnlineCustomerBucket($orderSourceId)) {
            return $this->ensureOnlineChannelCustomerAccount($customerName, $phone);
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
        ?int $companyId,
        ?int $orderSourceId = null
    ): ?TreeAccount {
        if ($this->isCompanyCustomerType($customerType) && $companyId) {
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
            ->where('type', 'asset')
            ->first();
    }

    // -------------------------------------------------------------------------
    // شجرة الحسابات — ذمم مدينة (عملاء)
    // -------------------------------------------------------------------------

    /**
     * «المدينون» تحت «الأصول المتداولة» حسب الشجرة الافتراضية للنظام.
     */
    private function findReceivablesControlAccount(): ?TreeAccount
    {
        $currentAssets = TreeAccount::query()
            ->where('name', 'الأصول المتداولة')
            ->where('type', 'asset')
            ->first();

        if (!$currentAssets) {
            return null;
        }

        return TreeAccount::query()
            ->where('parent_id', $currentAssets->id)
            ->where(function ($q) {
                $q->where('name', 'المدينون')
                    ->orWhere('name', 'like', '%مدينون%');
            })
            ->orderByRaw("CASE WHEN name = 'المدينون' THEN 0 ELSE 1 END")
            ->first();
    }

    /**
     * مجلد «العملاء» كوسيط تحت الأصول (اختبارات أو تركيبات قديمة) — يجب ألا يكون جذراً بدون أب.
     */
    private function findNestedLegacyCustomersFolder(): ?TreeAccount
    {
        return TreeAccount::query()
            ->where('type', 'asset')
            ->where('name', 'العملاء')
            ->whereNotNull('parent_id')
            ->orderByDesc('level')
            ->first();
    }

    /**
     * نقطة تثبيت ذمم العملاء: المدينون القياسي، أو مجلد العملاء القديم تحت الأصول.
     */
    private function receivableCustomersAnchor(): ?TreeAccount
    {
        return $this->findReceivablesControlAccount()
            ?? $this->findNestedLegacyCustomersFolder();
    }

    /**
     * إنشاء أو جلب حساب تجميعي (عملاء أفراد / شركات / أونلاين) تحت المدينون أو مجلد العملاء الوسيط.
     */
    private function ensureReceivableBucket(string $bucketName): ?TreeAccount
    {
        $anchor = $this->receivableCustomersAnchor();
        if (!$anchor) {
            return null;
        }

        $existing = TreeAccount::query()
            ->where('parent_id', $anchor->id)
            ->where('name', $bucketName)
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->createChildAccount($anchor, $bucketName, $anchor->type ?? 'asset');
    }

    private function fallbackCustomerParentByLegacyCodes(string $kind): ?TreeAccount
    {
        if ($kind === 'corporate') {
            $byCode = TreeAccount::where('code', 104)->where('level', 3)->first();
            if ($byCode) {
                return $byCode;
            }
        } else {
            $byCode = TreeAccount::where('code', 100025)->first();
            if ($byCode) {
                return $byCode;
            }
        }

        return null;
    }

    /**
     * @return 'corporate'|'online'|'individual'
     */
    private function normalizeCustomerKind(string $type): string
    {
        $t = trim(mb_strtolower($type));

        if (in_array($t, ['شركة', 'company'], true)) {
            return 'corporate';
        }
        if (in_array($t, ['أونلاين', 'اونلاين', 'online'], true)) {
            return 'online';
        }

        return 'individual';
    }

    private function isCompanyCustomerType(string $customerType): bool
    {
        return $this->normalizeCustomerKind($customerType) === 'corporate';
    }

    private function shouldUseOnlineCustomerBucket(?int $orderSourceId): bool
    {
        if (!$orderSourceId) {
            return false;
        }

        $src = OrderSource::query()->find($orderSourceId);
        if (!$src || $src->name === null || $src->name === '') {
            return false;
        }

        $n = mb_strtolower(trim($src->name), 'UTF-8');

        return (bool) preg_match(
            '/shopify|شوبify|أونلاين|اونلاين|online|ويب|web|internet|إنترنت|انترنت|e-?commerce|تجارة\s*إلكترونية/',
            $n
        );
    }

    // -------------------------------------------------------------------------
    // شجرة الحسابات — دائنون (موردين)
    // -------------------------------------------------------------------------

    private function findCreditorsControlAccount(): ?TreeAccount
    {
        $currentLiabilities = TreeAccount::query()
            ->where('name', 'الخصوم المتداولة')
            ->where('type', 'liability')
            ->first();

        if (!$currentLiabilities) {
            return TreeAccount::query()
                ->where('name', 'الدائنون')
                ->where('type', 'liability')
                ->whereNotNull('parent_id')
                ->first();
        }

        return TreeAccount::query()
            ->where('parent_id', $currentLiabilities->id)
            ->where(function ($q) {
                $q->where('name', 'الدائنون')
                    ->orWhere('name', 'like', '%دائنون%');
            })
            ->orderByRaw("CASE WHEN name = 'الدائنون' THEN 0 ELSE 1 END")
            ->first();
    }

    private function resolveDefaultSupplierParentAccount(): ?TreeAccount
    {
        $creditors = $this->findCreditorsControlAccount();
        if ($creditors) {
            $local = TreeAccount::query()
                ->where('parent_id', $creditors->id)
                ->where('name', 'موردين محليين')
                ->first();
            if ($local) {
                return $local;
            }

            $anySupplierish = TreeAccount::query()
                ->where('parent_id', $creditors->id)
                ->where(function ($q) {
                    $q->where('name', 'like', '%مورد%')
                        ->orWhere('name', 'like', '%موردين%');
                })
                ->orderBy('id')
                ->first();
            if ($anySupplierish) {
                return $anySupplierish;
            }

            return $this->createChildAccount($creditors, 'موردين محليين', 'liability');
        }

        return TreeAccount::query()
            ->where('name', 'موردين محليين')
            ->where('type', 'liability')
            ->whereNotNull('parent_id')
            ->first();
    }
}
