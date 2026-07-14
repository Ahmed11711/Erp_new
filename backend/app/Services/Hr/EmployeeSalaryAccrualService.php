<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeMonthAccrual;
use App\Models\EmployeeMonthPaid;
use App\Services\Accounting\AccountLinkingService;
use App\Services\Accounting\EmployeePaymentAccountingService;
use InvalidArgumentException;

class EmployeeSalaryAccrualService
{
    public function __construct(
        private AccountLinkingService $accountLinkingService,
        private EmployeePayrollNetSalaryService $payrollNetSalaryService,
        private EmployeePaymentAccountingService $employeePaymentAccountingService,
    ) {}

    /**
     * ترحيل استحقاق الراتب لحساب الموظف في الشجرة عند مراجعة الشهر.
     *
     * @return array{amount:float, accrual:EmployeeMonthAccrual|null, skipped:bool, reason?:string}
     */
    public function accrueForMonth(int $employeeId, int $month, int $year, ?float $overrideAmount = null): array
    {
        if (EmployeeMonthPaid::where('employee_id', $employeeId)
            ->where('month', $month)
            ->where('year', $year)
            ->exists()) {
            return ['amount' => 0, 'accrual' => null, 'skipped' => true, 'reason' => 'تم صرف الراتب'];
        }

        $employee = Employee::find($employeeId);
        if (! $employee) {
            throw new InvalidArgumentException('الموظف غير موجود');
        }

        $payableAccount = $this->accountLinkingService->resolveEmployeePayableAccount($employee);
        if (! $payableAccount) {
            throw new InvalidArgumentException('لم يُربط الموظف بحساب في شجرة الحسابات — اختر الحساب من بيانات الموظف');
        }

        $amount = $overrideAmount ?? $this->payrollNetSalaryService->calculateNetSalary($employee, $month, $year);
        if ($amount <= 0) {
            return ['amount' => 0, 'accrual' => null, 'skipped' => true, 'reason' => 'صافي الراتب صفر'];
        }

        $existing = EmployeeMonthAccrual::where('employee_id', $employeeId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if ($existing && abs($existing->amount - $amount) < 0.01) {
            return ['amount' => $amount, 'accrual' => $existing, 'skipped' => true, 'reason' => 'الترحيل مسجّل مسبقاً'];
        }

        $desc = 'استحقاق مرتب - '.($employee->name ?? 'موظف').' - '.$month.'/'.$year;

        if ($existing) {
            $diff = round($amount - (float) $existing->amount, 2);
            if (abs($diff) >= 0.01) {
                $entryId = $this->employeePaymentAccountingService->postAccrualAdjustment(
                    $desc.' (تعديل)',
                    abs($diff),
                    (int) $payableAccount->id,
                    $diff > 0
                );
                if (! $entryId) {
                    throw new InvalidArgumentException('تعذر تسجيل تعديل الاستحقاق المحاسبي');
                }
                $existing->amount = $amount;
                $existing->daily_entry_id = $entryId;
                $existing->user_id = auth()->id();
                $existing->save();
            }

            return ['amount' => $amount, 'accrual' => $existing, 'skipped' => false];
        }

        $entryId = $this->employeePaymentAccountingService->postSalaryAccrual(
            $desc,
            $amount,
            (int) $payableAccount->id
        );
        if (! $entryId) {
            throw new InvalidArgumentException('تعذر تسجيل قيد استحقاق الراتب — تحقق من حساب مصروف الرواتب');
        }

        $accrual = EmployeeMonthAccrual::create([
            'employee_id' => $employeeId,
            'month' => $month,
            'year' => $year,
            'amount' => $amount,
            'daily_entry_id' => $entryId,
            'user_id' => auth()->id(),
        ]);

        return ['amount' => $amount, 'accrual' => $accrual, 'skipped' => false];
    }

    /**
     * يضمن وجود ترحيل قبل الصرف — إن لم يُرحَّل يُنشأ تلقائياً.
     */
    public function ensureAccruedBeforePayment(int $employeeId, int $month, int $year, float $paymentAmount): void
    {
        $result = $this->accrueForMonth($employeeId, $month, $year, $paymentAmount);
        if ($result['skipped'] && ($result['reason'] ?? '') === 'تم صرف الراتب') {
            throw new InvalidArgumentException('تم دفع الراتب لهذا الشهر');
        }

        $accrual = EmployeeMonthAccrual::where('employee_id', $employeeId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if (! $accrual && $paymentAmount > 0) {
            throw new InvalidArgumentException('تعذر ترحيل استحقاق الراتب قبل الصرف');
        }
    }
}
