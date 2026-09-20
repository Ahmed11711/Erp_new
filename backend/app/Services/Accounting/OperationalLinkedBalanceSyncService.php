<?php

namespace App\Services\Accounting;

use App\Models\AccountEntry;
use App\Models\ShippingCompany;
use App\Models\Supplier;
use App\Models\TreeAccount;
use App\Models\customerCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * مزامنة الأرصدة التشغيلية للكيانات المرتبطة بحسابات شجرية
 * لتطابق رصيد GL (مدين − دائن) بعد استيراد ميزان المراجعة.
 */
class OperationalLinkedBalanceSyncService
{
    public function __construct(
        private PaymentSourceReconciliationService $paymentSourceRecon,
    ) {}

    /**
     * صافي GL من القيود: مدين − دائن.
     */
    public function glNet(int $treeAccountId): float
    {
        $row = AccountEntry::query()
            ->where('tree_account_id', $treeAccountId)
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) AS balance')
            ->first();

        return round((float) ($row->balance ?? 0), 2);
    }

    /**
     * مزامنة كل الكيانات المرتبطة بالحسابات المعطاة (أو الكل إن كانت القائمة فارغة).
     *
     * @param  list<int>  $treeAccountIds
     * @return array{
     *   synced: list<array<string, mixed>>,
     *   skipped: list<array<string, mixed>>,
     *   counts: array<string, int>
     * }
     */
    public function syncFromGl(array $treeAccountIds = [], ?string $reason = null): array
    {
        $reason = trim((string) ($reason ?: 'مزامنة بعد استيراد أرصدة افتتاحية'));
        $scope = array_values(array_unique(array_filter(array_map('intval', $treeAccountIds))));
        $synced = [];
        $skipped = [];

        $synced = array_merge($synced, $this->syncPaymentSources($scope, $reason, $skipped));
        $synced = array_merge($synced, $this->syncSuppliers($scope, $skipped));
        $synced = array_merge($synced, $this->syncCustomerCompanies($scope, $skipped));
        $synced = array_merge($synced, $this->syncShippingCompanies($scope, $skipped));

        $counts = [
            'synced' => count($synced),
            'skipped' => count($skipped),
            'safe' => 0,
            'bank' => 0,
            'service_account' => 0,
            'supplier' => 0,
            'customer_company' => 0,
            'shipping_company' => 0,
        ];
        foreach ($synced as $row) {
            $type = (string) ($row['type'] ?? '');
            if (isset($counts[$type])) {
                $counts[$type]++;
            }
        }

        return [
            'synced' => $synced,
            'skipped' => $skipped,
            'counts' => $counts,
        ];
    }

    /**
     * @param  list<int>  $scope
     * @param  list<array<string, mixed>>  $skipped
     * @return list<array<string, mixed>>
     */
    private function syncPaymentSources(array $scope, string $reason, array &$skipped): array
    {
        $out = [];
        $rows = $this->paymentSourceRecon->collectRows('all');

        foreach ($rows as $row) {
            $treeId = $row['tree_account_id'] ? (int) $row['tree_account_id'] : null;
            if (! $treeId) {
                $skipped[] = [
                    'type' => $row['type'],
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'reason' => 'غير مرتبط بحساب شجري',
                ];
                continue;
            }
            if ($scope !== [] && ! in_array($treeId, $scope, true)) {
                continue;
            }

            try {
                $result = $this->paymentSourceRecon->syncOperationalToGl(
                    (string) $row['type'],
                    (int) $row['id'],
                    $reason
                );
                if (abs(($result['after'] ?? 0) - ($result['before'] ?? 0)) < 0.01) {
                    continue;
                }
                $out[] = [
                    'type' => $row['type'],
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'tree_account_id' => $treeId,
                    'tree_code' => $row['tree_code'],
                    'before' => $result['before'],
                    'after' => $result['after'],
                    'gl' => $result['gl'],
                ];
            } catch (\Throwable $e) {
                Log::warning('operational sync payment source failed', [
                    'type' => $row['type'],
                    'id' => $row['id'],
                    'e' => $e->getMessage(),
                ]);
                $skipped[] = [
                    'type' => $row['type'],
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'reason' => $e->getMessage(),
                ];
            }
        }

        return $out;
    }

    /**
     * الموردون: الرصيد التشغيلي موجب عندما ندين للمورد ≈ −(مدين−دائن) للحساب الائتماني.
     *
     * @param  list<int>  $scope
     * @param  list<array<string, mixed>>  $skipped
     * @return list<array<string, mixed>>
     */
    private function syncSuppliers(array $scope, array &$skipped): array
    {
        $out = [];
        $q = Supplier::query()->whereNotNull('tree_account_id');
        if ($scope !== []) {
            $q->whereIn('tree_account_id', $scope);
        }

        foreach ($q->get(['id', 'supplier_name', 'balance', 'tree_account_id']) as $supplier) {
            $treeId = (int) $supplier->tree_account_id;
            $account = TreeAccount::find($treeId);
            if (! $account) {
                $skipped[] = [
                    'type' => 'supplier',
                    'id' => $supplier->id,
                    'name' => $supplier->supplier_name,
                    'reason' => 'الحساب الشجري غير موجود',
                ];
                continue;
            }

            $gl = $this->glNet($treeId);
            // خصوم/دائنون: الرصيد التشغيلي = القيمة الدائنة الصافية
            $target = $this->operationalFromGl($account, $gl);
            $before = round((float) $supplier->balance, 2);
            if (abs($target - $before) < 0.01) {
                continue;
            }

            DB::transaction(function () use ($supplier, $before, $target) {
                $locked = Supplier::query()->lockForUpdate()->find($supplier->id);
                if (! $locked) {
                    return;
                }
                $locked->last_balance = $before;
                $locked->balance = $target;
                $locked->save();
            });

            $out[] = [
                'type' => 'supplier',
                'id' => (int) $supplier->id,
                'name' => (string) $supplier->supplier_name,
                'tree_account_id' => $treeId,
                'tree_code' => (string) $account->code,
                'before' => $before,
                'after' => $target,
                'gl' => $gl,
            ];
        }

        return $out;
    }

    /**
     * عملاء الشركات: رصيد مدين تشغيلي ≈ صافي مدين−دائن.
     *
     * @param  list<int>  $scope
     * @param  list<array<string, mixed>>  $skipped
     * @return list<array<string, mixed>>
     */
    private function syncCustomerCompanies(array $scope, array &$skipped): array
    {
        $out = [];
        $q = customerCompany::query()->whereNotNull('tree_account_id');
        if ($scope !== []) {
            $q->whereIn('tree_account_id', $scope);
        }

        foreach ($q->get(['id', 'name', 'balance', 'tree_account_id']) as $company) {
            $treeId = (int) $company->tree_account_id;
            $account = TreeAccount::find($treeId);
            if (! $account) {
                $skipped[] = [
                    'type' => 'customer_company',
                    'id' => $company->id,
                    'name' => $company->name,
                    'reason' => 'الحساب الشجري غير موجود',
                ];
                continue;
            }

            $gl = $this->glNet($treeId);
            $target = $this->operationalFromGl($account, $gl);
            $before = round((float) $company->balance, 2);
            if (abs($target - $before) < 0.01) {
                continue;
            }

            customerCompany::query()->whereKey($company->id)->update(['balance' => $target]);

            $out[] = [
                'type' => 'customer_company',
                'id' => (int) $company->id,
                'name' => (string) $company->name,
                'tree_account_id' => $treeId,
                'tree_code' => (string) $account->code,
                'before' => $before,
                'after' => $target,
                'gl' => $gl,
            ];
        }

        return $out;
    }

    /**
     * المندوبون/شركات الشحن: عمود balance = ذمة COD ≈ حساب receivable_tree_account_id.
     *
     * @param  list<int>  $scope
     * @param  list<array<string, mixed>>  $skipped
     * @return list<array<string, mixed>>
     */
    private function syncShippingCompanies(array $scope, array &$skipped): array
    {
        $out = [];
        $q = ShippingCompany::query()->whereNotNull('receivable_tree_account_id');
        if ($scope !== []) {
            $q->whereIn('receivable_tree_account_id', $scope);
        }

        foreach ($q->get(['id', 'name', 'balance', 'receivable_tree_account_id']) as $company) {
            $treeId = (int) $company->receivable_tree_account_id;
            $account = TreeAccount::find($treeId);
            if (! $account) {
                $skipped[] = [
                    'type' => 'shipping_company',
                    'id' => $company->id,
                    'name' => $company->name,
                    'reason' => 'حساب الذمم المدينة غير موجود',
                ];
                continue;
            }

            $gl = $this->glNet($treeId);
            $target = $this->operationalFromGl($account, $gl);
            $before = round((float) ($company->getAttributes()['balance'] ?? 0), 2);
            if (abs($target - $before) < 0.01) {
                continue;
            }

            // balance غير موجود في $fillable — تحديث مباشر
            ShippingCompany::query()->whereKey($company->id)->update(['balance' => $target]);

            $out[] = [
                'type' => 'shipping_company',
                'id' => (int) $company->id,
                'name' => (string) $company->name,
                'tree_account_id' => $treeId,
                'tree_code' => (string) $account->code,
                'before' => $before,
                'after' => $target,
                'gl' => $gl,
            ];
        }

        return $out;
    }

    /**
     * تحويل صافي GL إلى الرصيد التشغيلي حسب طبيعة الحساب.
     * - أصول/مصروفات: التشغيلي = مدين−دائن
     * - خصوم/إيرادات/حقوق ملكية/تسوية: التشغيلي = دائن−مدين = −(مدين−دائن)
     */
    private function operationalFromGl(TreeAccount $account, float $glNet): float
    {
        $type = (string) $account->type;
        if (in_array($type, ['liability', 'equity', 'revenue', 'settlement'], true)) {
            return round(-1 * $glNet, 2);
        }

        return round($glNet, 2);
    }
}
