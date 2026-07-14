<?php

namespace App\Services\Accounting;

use App\Models\CollectionCompany;
use App\Models\OrderSource;
use App\Models\Setting;
use App\Models\ShippingCompany;
use App\Models\TreeAccount;
use App\Models\customerCompany;
use App\Models\Employee;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
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

    /** @deprecated حساب تجميعي قديم — يُستبدل بـ Shopify تحت عملاء أفراد */
    private const BUCKET_ONLINE_LEGACY = 'عملاء أونلاين';

    private const BUCKET_SHOPIFY = 'Shopify';

    /**
     * الحصول على الحساب الأب لعملاء الشركات من الإعدادات
     */
    public function getCustomerCorporateParent(): ?TreeAccount
    {
        $parentId = Setting::where('key', 'customer_corporate_parent_account_id')->value('value');
        if ($parentId) {
            $fromSetting = TreeAccount::find($parentId);
            if ($fromSetting) {
                return $fromSetting;
            }
        }

        $byBucket = TreeAccount::query()
            ->where('type', 'asset')
            ->where('name', self::BUCKET_CORPORATE)
            ->orderByRaw("CASE WHEN code = '1000235' OR code = 1000235 THEN 0 ELSE 1 END")
            ->orderBy('level')
            ->first();
        if ($byBucket) {
            return $byBucket;
        }

        return $this->ensureReceivableBucket(self::BUCKET_CORPORATE);
    }

    /**
     * الحصول على الحساب الأب لعملاء الأفراد من الإعدادات
     */
    public function getCustomerIndividualParent(): ?TreeAccount
    {
        $parentId = Setting::where('key', 'customer_individual_parent_account_id')->value('value');
        if ($parentId) {
            $fromSetting = TreeAccount::find($parentId);
            if ($fromSetting) {
                return $fromSetting;
            }
        }

        $byBucket = TreeAccount::query()
            ->where('type', 'asset')
            ->where('name', self::BUCKET_INDIVIDUALS)
            ->orderByRaw("CASE WHEN code = '1000234' OR code = 1000234 THEN 0 ELSE 1 END")
            ->orderBy('level')
            ->first();
        if ($byBucket) {
            return $byBucket;
        }

        return $this->ensureReceivableBucket(self::BUCKET_INDIVIDUALS);
    }

    /**
     * الحساب الأب لعملاء القنوات الإلكترونية (Shopify وغيرها) — اختياري في الإعدادات
     */
    public function getCustomerOnlineParent(): ?TreeAccount
    {
        return $this->getShopifyOnlineParent();
    }

    /**
     * حساب Shopify تحت «عملاء أفراد» — يُنشأ تلقائياً لعملاء الطلبات الإلكترونية.
     */
    public function getShopifyOnlineParent(): ?TreeAccount
    {
        $parentId = Setting::where('key', 'customer_online_parent_account_id')->value('value');
        if ($parentId) {
            $fromSetting = TreeAccount::find($parentId);
            if ($fromSetting) {
                return $fromSetting;
            }
        }

        return $this->ensureShopifyBucket();
    }

    /**
     * إنشاء أو جلب حساب Shopify تحت «عملاء أفراد».
     */
    public function ensureShopifyBucket(): ?TreeAccount
    {
        $individualParent = $this->getCustomerIndividualParent();
        if (!$individualParent) {
            return null;
        }

        $existing = TreeAccount::query()
            ->where('parent_id', $individualParent->id)
            ->where(function ($q) {
                $q->where('name', self::BUCKET_SHOPIFY)
                    ->orWhere('name', 'شوبيفاي');
            })
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->createChildAccount($individualParent, self::BUCKET_SHOPIFY, 'asset');
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
     * مزامنة اسم حساب الشجرة مع اسم الشركة بعد التعديل.
     */
    public function syncCustomerCompanyTreeAccountName(customerCompany $company): void
    {
        if (!$company->tree_account_id) {
            return;
        }

        $account = TreeAccount::find($company->tree_account_id);
        $desiredName = trim($company->name ?? '');
        if (!$account || $desiredName === '') {
            return;
        }

        if (trim($account->name) !== $desiredName) {
            $account->name = $desiredName;
            $account->save();
        }
    }

    /**
     * ربط جميع عملاء الشركات غير المربوطين بحسابات تحت «عملاء شركات».
     *
     * @return array{success:bool,message:string,linked:int,failed:int,total:int,failed_items:array<int,array{id:int,name:string}>,parent:array{id:int,name:string,code:string}|null}
     */
    public function linkAllUnlinkedCustomerCompanies(): array
    {
        $parent = $this->getCustomerCorporateParent();
        if (!$parent) {
            return [
                'success' => false,
                'message' => 'تعذر العثور على حساب تجميعي «عملاء شركات» في شجرة الحسابات.',
                'linked' => 0,
                'failed' => 0,
                'total' => 0,
                'failed_items' => [],
                'parent' => null,
            ];
        }

        $companies = customerCompany::query()
            ->whereNull('tree_account_id')
            ->orderBy('id')
            ->get();

        $linked = 0;
        $failedItems = [];

        foreach ($companies as $company) {
            $account = $this->ensureCustomerCompanyAccount($company);
            if ($account) {
                $linked++;
            } else {
                $failedItems[] = [
                    'id' => $company->id,
                    'name' => $company->name,
                ];
            }
        }

        $failed = count($failedItems);
        $total = $companies->count();

        return [
            'success' => $failed === 0,
            'message' => $total === 0
                ? 'جميع عملاء الشركات مربوطون بالفعل.'
                : "تم ربط {$linked} من {$total} شركة.",
            'linked' => $linked,
            'failed' => $failed,
            'total' => $total,
            'failed_items' => $failedItems,
            'parent' => [
                'id' => $parent->id,
                'name' => $parent->name,
                'code' => (string) $parent->code,
            ],
        ];
    }

    /**
     * الحساب الأب لذمم شركات الشحن والمناديب (أصول).
     */
    public function getShippingReceivableParent(): ?TreeAccount
    {
        return $this->resolveShippingReceivableParent();
    }

    /**
     * الحساب الأب لشركات التحصيل (أصول).
     */
    public function getCollectionCompaniesParent(): ?TreeAccount
    {
        return $this->resolveCollectionCompaniesParent();
    }

    /**
     * إنشاء أو الحصول على حساب ذمم (أصول) لشركة شحن أو مندوب.
     */
    public function ensureShippingCompanyAccount(ShippingCompany $company): ?TreeAccount
    {
        $guard = app(ReceivableTreeAccountGuard::class);

        if ($company->receivable_tree_account_id) {
            $existing = TreeAccount::find($company->receivable_tree_account_id);
            if ($existing && ! $guard->isPaymentSourceTreeAccount((int) $existing->id)) {
                return $existing;
            }

            if ($existing && $guard->isPaymentSourceTreeAccount((int) $existing->id)) {
                Log::warning('AccountLinkingService: shipping company receivable linked to payment source — re-linking', [
                    'shipping_company_id' => $company->id,
                    'invalid_tree_account_id' => $existing->id,
                ]);
                $company->receivable_tree_account_id = null;
                $company->save();
            }
        }

        $parent = $this->resolveShippingReceivableParent();
        if (!$parent) {
            Log::error('AccountLinkingService: cannot resolve parent for shipping company tree account', [
                'shipping_company_id' => $company->id,
            ]);

            return null;
        }

        $name = trim((string) ($company->name ?? '')) !== ''
            ? $company->name
            : ('شركة شحن ' . $company->id);

        $account = $this->createChildAccount($parent, $name, 'asset');
        $company->receivable_tree_account_id = $account->id;
        $company->save();

        return $account;
    }

    /**
     * ذمم دائن (خصوم): مستحق للمندوب/شركة الشحن — شحن توريد/مشتريات.
     * منفصل عن receivable_tree_account_id (ذمم مدين — تحصيل COD).
     */
    public function ensureShippingCompanyFreightPayableAccount(ShippingCompany $company): ?TreeAccount
    {
        if ($company->tree_account_id) {
            $existing = TreeAccount::find($company->tree_account_id);
            if ($existing) {
                return $existing;
            }
        }

        try {
            $parent = TreeAccount::ensureShippingCourierPayableAccount();
        } catch (\Throwable $e) {
            Log::error('AccountLinkingService: cannot resolve freight payable parent for shipping company', [
                'shipping_company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $name = trim((string) ($company->name ?? '')) !== ''
            ? $company->name
            : ('شركة شحن ' . $company->id);

        $account = $this->createChildAccount($parent, $name, 'liability');
        $company->tree_account_id = $account->id;
        $company->save();

        return $account;
    }

    /**
     * مزامنة اسم حساب الذمم مع اسم شركة الشحن/المندوب بعد التعديل.
     */
    public function syncShippingCompanyTreeAccountName(ShippingCompany $company): void
    {
        if (!$company->receivable_tree_account_id) {
            return;
        }

        $account = TreeAccount::find($company->receivable_tree_account_id);
        $desiredName = trim($company->name ?? '');
        if (!$account || $desiredName === '') {
            return;
        }

        if (trim($account->name) !== $desiredName) {
            $account->name = $desiredName;
            $account->save();
        }
    }

    /**
     * ربط جميع شركات الشحن والمناديب غير المربوطين بحسابات تحت «شركات الشحن والمناديب».
     *
     * @return array{success:bool,message:string,linked:int,failed:int,total:int,failed_items:array<int,array{id:int,name:string}>,parent:array{id:int,name:string,code:string}|null}
     */
    public function linkAllUnlinkedShippingCompanies(): array
    {
        $parent = $this->resolveShippingReceivableParent();
        if (!$parent) {
            return [
                'success' => false,
                'message' => 'تعذر العثور على حساب تجميعي «شركات الشحن والمناديب» في شجرة الحسابات.',
                'linked' => 0,
                'failed' => 0,
                'total' => 0,
                'failed_items' => [],
                'parent' => null,
            ];
        }

        $guard = app(ReceivableTreeAccountGuard::class);
        $companies = ShippingCompany::query()
            ->orderBy('id')
            ->get()
            ->filter(function (ShippingCompany $company) use ($guard) {
                if (! $company->receivable_tree_account_id) {
                    return true;
                }

                return $guard->isPaymentSourceTreeAccount((int) $company->receivable_tree_account_id);
            });

        $linked = 0;
        $failedItems = [];

        foreach ($companies as $company) {
            $account = $this->ensureShippingCompanyAccount($company);
            if ($account) {
                $linked++;
            } else {
                $failedItems[] = [
                    'id' => $company->id,
                    'name' => $company->name,
                ];
            }
        }

        $failed = count($failedItems);
        $total = $companies->count();

        return [
            'success' => $failed === 0,
            'message' => $total === 0
                ? 'جميع شركات الشحن والمناديب مربوطون بالفعل.'
                : "تم ربط {$linked} من {$total} شركة/مندوب.",
            'linked' => $linked,
            'failed' => $failed,
            'total' => $total,
            'failed_items' => $failedItems,
            'parent' => [
                'id' => $parent->id,
                'name' => $parent->name,
                'code' => (string) $parent->code,
            ],
        ];
    }

    /**
     * مزامنة اسم حساب الذمم مع اسم شركة التحصيل بعد التعديل.
     */
    public function syncCollectionCompanyTreeAccountName(CollectionCompany $company): void
    {
        if (!$company->receivable_tree_account_id) {
            return;
        }

        $account = TreeAccount::find($company->receivable_tree_account_id);
        $desiredName = trim($company->name ?? '');
        if (!$account || $desiredName === '') {
            return;
        }

        if (trim($account->name) !== $desiredName) {
            $account->name = $desiredName;
            $account->save();
        }
    }

    /**
     * ربط جميع شركات التحصيل غير المربوطين بحسابات تحت «شركات التحصيل».
     *
     * @return array{success:bool,message:string,linked:int,failed:int,total:int,failed_items:array<int,array{id:int,name:string}>,parent:array{id:int,name:string,code:string}|null}
     */
    public function linkAllUnlinkedCollectionCompanies(): array
    {
        $parent = $this->resolveCollectionCompaniesParent();
        if (!$parent) {
            return [
                'success' => false,
                'message' => 'تعذر العثور على حساب تجميعي «شركات التحصيل» في شجرة الحسابات.',
                'linked' => 0,
                'failed' => 0,
                'total' => 0,
                'failed_items' => [],
                'parent' => null,
            ];
        }

        $guard = app(ReceivableTreeAccountGuard::class);
        $companies = CollectionCompany::query()
            ->orderBy('id')
            ->get()
            ->filter(function (CollectionCompany $company) use ($guard) {
                if (! $company->receivable_tree_account_id) {
                    return true;
                }

                return $guard->isPaymentSourceTreeAccount((int) $company->receivable_tree_account_id);
            });

        $linked = 0;
        $failedItems = [];

        foreach ($companies as $company) {
            $account = $this->ensureCollectionCompanyAccount($company);
            if ($account) {
                $linked++;
            } else {
                $failedItems[] = [
                    'id' => $company->id,
                    'name' => $company->name,
                ];
            }
        }

        $failed = count($failedItems);
        $total = $companies->count();

        return [
            'success' => $failed === 0,
            'message' => $total === 0
                ? 'جميع شركات التحصيل مربوطة بالفعل.'
                : "تم ربط {$linked} من {$total} شركة تحصيل.",
            'linked' => $linked,
            'failed' => $failed,
            'total' => $total,
            'failed_items' => $failedItems,
            'parent' => [
                'id' => $parent->id,
                'name' => $parent->name,
                'code' => (string) $parent->code,
            ],
        ];
    }

    /**
     * إنشاء أو الحصول على حساب ذمم (أصول) لشركة تحصيل إلكتروني (Paymob / valU / Sympl …).
     * المبلغ المحصّل إلكترونياً يُسجَّل مديونية على هذا الحساب حتى تُسدّده الشركة للنظام.
     */
    public function ensureCollectionCompanyAccount(CollectionCompany $company): ?TreeAccount
    {
        $guard = app(ReceivableTreeAccountGuard::class);

        if ($company->receivable_tree_account_id) {
            $existing = TreeAccount::find($company->receivable_tree_account_id);
            if ($existing && ! $guard->isPaymentSourceTreeAccount((int) $existing->id)) {
                return $existing;
            }

            if ($existing && $guard->isPaymentSourceTreeAccount((int) $existing->id)) {
                Log::warning('AccountLinkingService: collection company receivable linked to payment source — re-linking', [
                    'collection_company_id' => $company->id,
                    'invalid_tree_account_id' => $existing->id,
                ]);
                $company->receivable_tree_account_id = null;
                $company->save();
            }
        }

        $parent = $this->resolveCollectionCompaniesParent();
        if (!$parent) {
            Log::error('AccountLinkingService: cannot resolve parent for collection company tree account', [
                'collection_company_id' => $company->id,
            ]);

            return null;
        }

        $name = trim((string) ($company->name ?? '')) !== ''
            ? $company->name
            : ('شركة تحصيل ' . $company->id);

        $account = $this->createChildAccount($parent, $name, 'asset');
        $company->receivable_tree_account_id = $account->id;
        $company->save();

        return $account;
    }

    /**
     * الحساب الأب لذمم شركات الشحن والمناديب: من الإعدادات إن وُجد، وإلا حساب تجميعي تحت المدينون.
     */
    private function resolveShippingReceivableParent(): ?TreeAccount
    {
        $settingKey = (string) config('shipping_receivable.parent_account_setting_key', 'shipping_receivable_parent_account_id');
        if ($settingKey !== '') {
            $parentId = Setting::where('key', $settingKey)->value('value');
            if ($parentId) {
                $fromSetting = TreeAccount::find($parentId);
                if ($fromSetting) {
                    return $fromSetting;
                }
            }
        }

        $bucketName = (string) config('shipping_receivable.parent_account_name', 'شركات الشحن والمناديب');

        return $this->ensureReceivableBucket($bucketName);
    }

    /**
     * الحساب الأب لشركات التحصيل: من الإعدادات إن وُجد، وإلا حساب تجميعي تحت المدينون.
     */
    private function resolveCollectionCompaniesParent(): ?TreeAccount
    {
        $settingKey = (string) config('shopify_collection.parent_account_setting_key', 'collection_companies_parent_account_id');
        if ($settingKey !== '') {
            $parentId = Setting::where('key', $settingKey)->value('value');
            if ($parentId) {
                $fromSetting = TreeAccount::find($parentId);
                if ($fromSetting) {
                    return $fromSetting;
                }
            }
        }

        $bucketName = (string) config('shopify_collection.parent_account_name', 'شركات التحصيل');

        return $this->ensureReceivableBucket($bucketName);
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
     * مزامنة اسم حساب شجرة المورد مع اسم المورد
     */
    public function syncSupplierTreeAccountName(Supplier $supplier): void
    {
        if (! $supplier->tree_account_id) {
            return;
        }

        $account = TreeAccount::find($supplier->tree_account_id);
        $desiredName = trim($supplier->supplier_name ?? '');
        if (! $account || $desiredName === '') {
            return;
        }

        if (trim($account->name) !== $desiredName) {
            $account->name = $desiredName;
            $account->save();
        }
    }

    private const BUCKET_EMPLOYEE_PAYABLE = 'رواتب مستحقة';

    /**
     * الحساب الأب لذمم رواتب الموظفين (خصوم).
     */
    public function getEmployeePayableParent(): ?TreeAccount
    {
        $parentId = Setting::where('key', 'employee_payable_parent_account_id')->value('value');
        if ($parentId) {
            $fromSetting = TreeAccount::find($parentId);
            if ($fromSetting) {
                return $fromSetting;
            }
        }

        $byBucket = TreeAccount::query()
            ->where('type', 'liability')
            ->where('name', self::BUCKET_EMPLOYEE_PAYABLE)
            ->orderBy('level')
            ->first();
        if ($byBucket) {
            return $byBucket;
        }

        return $this->ensureLiabilityBucket(self::BUCKET_EMPLOYEE_PAYABLE);
    }

    /**
     * الحصول على حساب الموظف المربوط يدوياً من شجرة الحسابات.
     */
    public function resolveEmployeePayableAccount(Employee $employee): ?TreeAccount
    {
        if (! $employee->payable_tree_account_id) {
            return null;
        }

        return TreeAccount::find($employee->payable_tree_account_id);
    }

    /**
     * إنشاء أو الحصول على حساب مستحقات (خصوم) لموظف — للربط الجماعي فقط.
     */
    public function ensureEmployeePayableAccount(Employee $employee): ?TreeAccount
    {
        $existing = $this->resolveEmployeePayableAccount($employee);
        if ($existing) {
            return $existing;
        }

        $parent = $this->getEmployeePayableParent();
        if (! $parent) {
            Log::error('AccountLinkingService: cannot resolve parent for employee payable tree account', [
                'employee_id' => $employee->id,
            ]);

            return null;
        }

        $name = trim((string) ($employee->name ?? '')) !== ''
            ? $employee->name
            : ('موظف '.$employee->id);

        $account = $this->createChildAccount($parent, $name, 'liability');
        $employee->payable_tree_account_id = $account->id;
        $employee->save();

        return $account;
    }

    public function syncEmployeePayableTreeAccountName(Employee $employee): void
    {
        if (! $employee->payable_tree_account_id) {
            return;
        }

        $account = TreeAccount::find($employee->payable_tree_account_id);
        $desiredName = trim($employee->name ?? '');
        if (! $account || $desiredName === '') {
            return;
        }

        if (trim($account->name) !== $desiredName) {
            $account->name = $desiredName;
            $account->save();
        }
    }

    /**
     * @return array{success:bool,message:string,linked:int,failed:int,total:int}
     */
    public function linkAllUnlinkedEmployees(): array
    {
        $linked = 0;
        $failed = 0;
        $employees = Employee::query()->whereNull('payable_tree_account_id')->get();

        foreach ($employees as $employee) {
            $account = $this->ensureEmployeePayableAccount($employee);
            if ($account) {
                $linked++;
            } else {
                $failed++;
            }
        }

        return [
            'success' => $failed === 0,
            'message' => "تم ربط {$linked} موظف".($failed > 0 ? " — فشل {$failed}" : ''),
            'linked' => $linked,
            'failed' => $failed,
            'total' => $employees->count(),
        ];
    }

    /**
     * إنشاء حساب فرعي تحت الحساب الأب
     */
    public function createChildAccount(TreeAccount $parent, string $name, string $type): TreeAccount
    {
        return DB::transaction(function () use ($parent, $name, $type) {
            $parent = TreeAccount::query()->whereKey($parent->id)->lockForUpdate()->firstOrFail();
            $lastChild = TreeAccount::queryLastChildUnderParentLocked($parent);
            $resolved = TreeAccount::resolveNextChildCodeAndLevel($parent, $lastChild);
            $code = $resolved['code'];

            while (TreeAccount::where('code', $code)->exists()) {
                $code = (string) ((int) preg_replace('/\D/', '', $code) + 1);
            }

            return TreeAccount::create([
                'name' => $name,
                'parent_id' => $parent->id,
                'code' => $code,
                'type' => $type ?? $parent->type,
                'balance' => 0,
                'debit_balance' => 0,
                'credit_balance' => 0,
                'level' => $resolved['level'],
            ]);
        });
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
                'online' => $this->getShopifyOnlineParent(),
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
     * عميل فردي من قناة إلكترونية — تحت Shopify ← عملاء أفراد (أو الإعداد المخصص).
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
                    ->orWhere('name', 'العملاء')
                    ->orWhere('name', 'like', '%مدينون%');
            })
            ->orderByRaw("CASE WHEN name = 'المدينون' THEN 0 WHEN name = 'العملاء' THEN 1 ELSE 2 END")
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
     * نقطة تثبيت ذمم العملاء: مجلد «العملاء» إن وُجدت تحته فروع التجميع، وإلا المدينون القياسي.
     */
    private function receivableCustomersAnchor(): ?TreeAccount
    {
        $legacyCustomers = $this->findNestedLegacyCustomersFolder();
        if ($legacyCustomers && $this->hasCustomerBucketsUnder($legacyCustomers)) {
            return $legacyCustomers;
        }

        return $this->findReceivablesControlAccount()
            ?? $legacyCustomers;
    }

    private function hasCustomerBucketsUnder(TreeAccount $parent): bool
    {
        return TreeAccount::query()
            ->where('parent_id', $parent->id)
            ->whereIn('name', [
                self::BUCKET_INDIVIDUALS,
                self::BUCKET_CORPORATE,
                self::BUCKET_ONLINE_LEGACY,
            ])
            ->exists();
    }

    /**
     * إنشاء أو جلب حساب تجميعي (عملاء أفراد / شركات) تحت مجلد العملاء أو المدينون.
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

    /**
     * إنشاء أو جلب حساب تجميعي تحت الخصوم المتداولة (مثل رواتب مستحقة).
     */
    private function ensureLiabilityBucket(string $bucketName): ?TreeAccount
    {
        $anchor = $this->findCurrentLiabilitiesAnchor();
        if (! $anchor) {
            return null;
        }

        $existing = TreeAccount::query()
            ->where('parent_id', $anchor->id)
            ->where('name', $bucketName)
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->createChildAccount($anchor, $bucketName, $anchor->type ?? 'liability');
    }

    private function findCurrentLiabilitiesAnchor(): ?TreeAccount
    {
        $currentLiabilities = TreeAccount::query()
            ->where('name', 'الخصوم المتداولة')
            ->where('type', 'liability')
            ->first();

        if (! $currentLiabilities) {
            return TreeAccount::query()
                ->where('type', 'liability')
                ->where('level', 2)
                ->orderBy('id')
                ->first();
        }

        $accrued = TreeAccount::query()
            ->where('parent_id', $currentLiabilities->id)
            ->where(function ($q) {
                $q->where('name', 'المصروفات المستحقة')
                    ->orWhere('name', 'like', '%مستحقة%');
            })
            ->first();

        return $accrued ?? $currentLiabilities;
    }

    private function fallbackCustomerParentByLegacyCodes(string $kind): ?TreeAccount
    {
        if ($kind === 'corporate') {
            foreach (['1000235', 1000235] as $code) {
                $byCode = TreeAccount::where('code', $code)->first();
                if ($byCode) {
                    return $byCode;
                }
            }

            return TreeAccount::query()
                ->where('type', 'asset')
                ->where('name', self::BUCKET_CORPORATE)
                ->first();
        }

        foreach (['1000234', 1000234, '100025', 100025] as $code) {
            $byCode = TreeAccount::where('code', $code)->first();
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
