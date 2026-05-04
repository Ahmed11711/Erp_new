<?php

namespace App\Services\Hr;

use App\Models\Bank;
use App\Models\Employee;
use App\Models\EmployeeMonthPaid;
use App\Models\Safe;
use App\Models\SafeTransaction;
use App\Models\ServiceAccount;
use App\Services\Accounting\EmployeePaymentAccountingService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EmployeeSalaryDisbursementService
{
    public function __construct(
        private EmployeePaymentAccountingService $employeePaymentAccountingService
    ) {}

    /**
     * @return array{0: EmployeeMonthPaid, 1: Employee}
     *
     * @throws InvalidArgumentException
     */
    public function disburseSingle(
        int $employeeId,
        float $amount,
        int $month,
        int $year,
        string $sourceType,
        int $sourceId
    ): array {
        $sourceType = $this->normalizeSourceType($sourceType);

        $exist = EmployeeMonthPaid::where('employee_id', $employeeId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();
        if ($exist) {
            throw new InvalidArgumentException('تم دفع الراتب لهذا الشهر');
        }

        $isAbsence = \App\Models\EmployeeSubtraction::where('employee_id', $employeeId)
            ->where('type', 'غياب')
            ->whereNull('absence_status')
            ->first();
        if ($isAbsence) {
            throw new InvalidArgumentException('يرجي مراجعة كشف الغياب لهذا الشهر');
        }

        $employeeData = Employee::find($employeeId);
        if (!$employeeData) {
            throw new InvalidArgumentException('الموظف غير موجود');
        }

        $desc = 'صرف مرتب - '.($employeeData->name ?? 'موظف').' - '.$month.'/'.$year;
        $glNote = match ($sourceType) {
            'bank' => 'صرف من البنك',
            'safe' => 'صرف من الخزينة',
            default => 'صرف من حساب خدمي',
        };

        $creditTreeId = $this->resolveCreditTreeAccountId($sourceType, $sourceId);
        $this->assertSufficientBalance($sourceType, $sourceId, $amount);

        $row = [
            'amount' => $amount,
            'month' => $month,
            'year' => $year,
            'employee_id' => $employeeId,
            'user_id' => auth()->id(),
            'payment_source' => $sourceType,
            'bank_id' => $sourceType === 'bank' ? $sourceId : null,
            'safe_id' => $sourceType === 'safe' ? $sourceId : null,
            'service_account_id' => $sourceType === 'service_account' ? $sourceId : null,
        ];

        $paid = EmployeeMonthPaid::create($row);

        $this->decrementOperationalBalance($sourceType, $sourceId, $amount, $employeeData, $month, $year);

        $posted = $this->employeePaymentAccountingService->postPaymentToCreditAccount(
            $desc,
            $amount,
            $creditTreeId,
            $glNote
        );
        if (! $posted) {
            throw new InvalidArgumentException('تعذر تسجيل القيد المحاسبي — تحقق من ربط المصدر بشجرة الحسابات وحساب مصروف الرواتب');
        }

        return [$paid, $employeeData];
    }

    public function totalAvailableBalance(string $sourceType, int $sourceId): float
    {
        $sourceType = $this->normalizeSourceType($sourceType);

        return match ($sourceType) {
            'bank' => (float) (Bank::find($sourceId)?->balance ?? 0),
            'safe' => (float) (Safe::find($sourceId)?->balance ?? 0),
            'service_account' => (float) (ServiceAccount::find($sourceId)?->balance ?? 0),
            default => 0.0,
        };
    }

    private function normalizeSourceType(string $sourceType): string
    {
        $t = strtolower(trim($sourceType));
        if ($t === 'service_account' || $t === 'service') {
            return 'service_account';
        }
        if (! in_array($t, ['bank', 'safe', 'service_account'], true)) {
            throw new InvalidArgumentException('نوع مصدر الصرف غير صالح');
        }

        return $t;
    }

    private function resolveCreditTreeAccountId(string $sourceType, int $sourceId): int
    {
        if ($sourceType === 'bank') {
            $bank = Bank::find($sourceId);
            if (! $bank || ! $bank->asset_id) {
                throw new InvalidArgumentException('البنك غير موجود أو غير مرتبط بحساب في الشجرة');
            }

            return (int) $bank->asset_id;
        }
        if ($sourceType === 'safe') {
            $safe = Safe::find($sourceId);
            if (! $safe || ! $safe->account_id) {
                throw new InvalidArgumentException('الخزينة غير موجودة أو غير مرتبطة بحساب في الشجرة');
            }

            return (int) $safe->account_id;
        }
        $svc = ServiceAccount::find($sourceId);
        if (! $svc || ! $svc->account_id) {
            throw new InvalidArgumentException('الحساب الخدمي غير موجود أو غير مرتبط بشجرة الحسابات');
        }

        return (int) $svc->account_id;
    }

    private function assertSufficientBalance(string $sourceType, int $sourceId, float $amount): void
    {
        $bal = $this->totalAvailableBalance($sourceType, $sourceId);
        if ($bal + 0.000001 < $amount) {
            throw new InvalidArgumentException('رصيد المصدر غير كافٍ لصرف هذا المبلغ');
        }
    }

    private function decrementOperationalBalance(
        string $sourceType,
        int $sourceId,
        float $amount,
        Employee $employeeData,
        int $month,
        int $year
    ): void {
        if ($sourceType === 'bank') {
            $bank = Bank::findOrFail($sourceId);
            $balanceBefore = (float) $bank->balance;
            $bank->balance = $bank->balance - $amount;
            $bank->save();

            DB::table('bank_details')->insert([
                'bank_id' => $bank->id,
                'details' => ' صرف مرتب '.$employeeData->name.' بتاريخ '.date('Y-m-d').' عن شهر '.$month,
                'ref' => '-',
                'type' => 'صرف مرتب',
                'amount' => (double) $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $bank->balance,
                'date' => date('Y-m-d'),
                'created_at' => now(),
                'user_id' => auth()->id(),
            ]);

            return;
        }
        if ($sourceType === 'safe') {
            $safe = Safe::findOrFail($sourceId);
            SafeTransaction::create([
                'date' => now()->toDateString(),
                'type' => 'withdrawal',
                'from_safe_id' => $safe->id,
                'to_safe_id' => null,
                'amount' => $amount,
                'notes' => 'صرف مرتب '.$employeeData->name.' — '.$month.'/'.$year,
                'user_id' => auth()->id(),
            ]);
            $safe->decrement('balance', $amount);

            return;
        }
        $svc = ServiceAccount::findOrFail($sourceId);
        $svc->decrement('balance', $amount);
    }
}
