<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeFingerPrintSheet;
use App\Models\EmployeeMerits;
use App\Models\EmployeeMonthAccrual;
use App\Models\EmployeeMonthPaid;
use App\Models\EmployeeSubtraction;
use App\Services\Hr\FingerprintHoursHelper;
use App\Services\Items\LimitedExcelReadFilter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * استيراد صافي/مستحق الراتب وتفاصيل (أيام/ساعات إضافية وخصومات) من Excel لشهر محدد.
 */
class PayrollAccrualImportService
{
    public const REASON_EXTRA_DAYS = 'استيراد Excel: أيام إضافية';

    public const REASON_OVERTIME = 'استيراد Excel: ساعات إضافية';

    public const REASON_REWARDS = 'استيراد Excel: مكافآت';

    public const REASON_ALLOWANCES = 'استيراد Excel: بدلات';

    public const REASON_DEDUCTIONS = 'استيراد Excel: خصومات';

    public const REASON_ADVANCE = 'استيراد Excel: سلفة';

    public function __construct(
        private EmployeeSalaryAccrualService $accrualService,
    ) {}

    /**
     * @return array{
     *   matched: list<array<string, mixed>>,
     *   missing: list<array<string, mixed>>,
     *   skipped: list<array<string, mixed>>,
     *   totals: array<string, float|int>
     * }
     */
    public function preview(UploadedFile $file, int $month, int $year): array
    {
        $rows = $this->parseSpreadsheet($file);
        $employees = Employee::query()
            ->get(['id', 'name', 'code', 'acc_no', 'payable_tree_account_id']);

        $byCode = [];
        $byAccNo = [];
        $byName = [];
        foreach ($employees as $emp) {
            $codeKey = $this->normalizeCode((string) ($emp->code ?? ''));
            if ($codeKey !== '') {
                $byCode[$codeKey] = $emp;
            }
            $accKey = $this->normalizeCode((string) ($emp->acc_no ?? ''));
            if ($accKey !== '') {
                $byAccNo[$accKey] = $emp;
            }
            $nameKey = $this->normalizeName((string) ($emp->name ?? ''));
            if ($nameKey !== '') {
                $byName[$nameKey][] = $emp;
            }
        }

        $employeeIds = $employees->pluck('id')->all();
        $accruals = EmployeeMonthAccrual::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('month', $month)
            ->where('year', $year)
            ->get()
            ->keyBy('employee_id');
        $paidIds = EmployeeMonthPaid::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('month', $month)
            ->where('year', $year)
            ->pluck('employee_id')
            ->flip();

        $matched = [];
        $missing = [];
        $skipped = [];
        $seenEmployeeIds = [];
        $totalAmount = 0.0;

        foreach ($rows as $row) {
            $code = $row['employee_code'];
            $accNo = $row['fingerprint_no'];
            $name = $row['employee_name'];
            $amount = $row['amount'];
            $hasDetails = $this->rowHasDetails($row);

            if ($amount < 0.01 && ! $hasDetails) {
                $skipped[] = array_merge($row, ['reason' => 'مبلغ صفر أو غير صالح']);
                continue;
            }

            $employee = null;
            $matchBy = null;
            $missReason = null;
            [$employee, $matchBy, $missReason] = $this->resolveEmployee(
                $code,
                $accNo,
                $name,
                $byCode,
                $byAccNo,
                $byName
            );

            if (! $employee) {
                $missing[] = array_merge($row, ['reason' => $missReason ?? 'الموظف غير موجود في النظام']);
                continue;
            }

            $employeeId = (int) $employee->id;
            if (isset($seenEmployeeIds[$employeeId])) {
                $skipped[] = array_merge($row, [
                    'employee_id' => $employeeId,
                    'reason' => 'تكرار لنفس الموظف في الملف',
                ]);
                continue;
            }
            $seenEmployeeIds[$employeeId] = true;

            $currentAccrual = $accruals->get($employeeId);
            $alreadyPaid = $paidIds->has($employeeId);
            $hasPayableAccount = ! empty($employee->payable_tree_account_id);
            $canApply = ! $alreadyPaid && ($amount >= 0.01 || $hasDetails);

            $payload = [
                'excel_row' => $row['excel_row'],
                'employee_id' => $employeeId,
                'employee_code' => (string) ($employee->code ?? ''),
                'employee_name' => (string) ($employee->name ?? ''),
                'excel_code' => $code,
                'excel_fingerprint' => $accNo,
                'excel_name' => $name,
                'amount' => $amount,
                'extra_day_value' => $row['extra_day_value'],
                'overtime_value' => $row['overtime_value'],
                'rewards' => $row['rewards'],
                'allowances' => $row['allowances'],
                'deductions' => $row['deductions'],
                'advance' => $row['advance'],
                'current_accrual' => $currentAccrual ? round((float) $currentAccrual->amount, 2) : null,
                'already_paid' => $alreadyPaid,
                'has_payable_account' => $hasPayableAccount,
                'will_auto_link_account' => false,
                'match_by' => $matchBy,
                'can_apply' => $canApply,
            ];

            if ($alreadyPaid) {
                $payload['reason'] = 'تم صرف الراتب لهذا الشهر';
            } elseif (! $hasPayableAccount && $amount >= 0.01) {
                $payload['reason'] = 'جاهز (بدون قيد محاسبي — لا يوجد ربط حساب)';
            }

            $matched[] = $payload;
            $totalAmount += $amount;
        }

        $payableCount = count(array_filter($matched, fn (array $r) => ! empty($r['can_apply'])));

        return [
            'matched' => $matched,
            'missing' => $missing,
            'skipped' => $skipped,
            'totals' => [
                'matched_count' => count($matched),
                'missing_count' => count($missing),
                'skipped_count' => count($skipped),
                'payable_count' => $payableCount,
                'total_amount' => round($totalAmount, 2),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array{
     *   updated: list<array<string, mixed>>,
     *   skipped: list<array<string, mixed>>,
     *   failed: list<array<string, mixed>>,
     *   totals: array<string, int>
     * }
     */
    public function apply(int $month, int $year, array $lines): array
    {
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException('الشهر غير صالح');
        }
        if ($year < 2000 || $year > 2100) {
            throw new \InvalidArgumentException('السنة غير صالحة');
        }
        if ($lines === []) {
            throw new \InvalidArgumentException('لا توجد صفوف للتطبيق');
        }

        $updated = [];
        $skipped = [];
        $failed = [];

        foreach ($lines as $line) {
            $employeeId = (int) ($line['employee_id'] ?? 0);
            $amount = round((float) ($line['amount'] ?? 0), 2);
            $details = [
                'extra_day_value' => round((float) ($line['extra_day_value'] ?? 0), 2),
                'overtime_value' => round((float) ($line['overtime_value'] ?? 0), 2),
                'rewards' => round((float) ($line['rewards'] ?? 0), 2),
                'allowances' => round((float) ($line['allowances'] ?? 0), 2),
                'deductions' => round((float) ($line['deductions'] ?? 0), 2),
                'advance' => round((float) ($line['advance'] ?? 0), 2),
            ];
            $hasDetails = $this->rowHasDetails($details);

            if ($employeeId <= 0 || ($amount < 0.01 && ! $hasDetails)) {
                $skipped[] = [
                    'employee_id' => $employeeId,
                    'amount' => $amount,
                    'reason' => 'بيانات غير صالحة',
                ];
                continue;
            }

            if (EmployeeMonthPaid::where('employee_id', $employeeId)
                ->where('month', $month)
                ->where('year', $year)
                ->exists()) {
                $skipped[] = [
                    'employee_id' => $employeeId,
                    'amount' => $amount,
                    'reason' => 'تم صرف الراتب لهذا الشهر',
                ];
                continue;
            }

            try {
                $result = DB::transaction(function () use ($employeeId, $month, $year, $amount, $details) {
                    FingerprintHoursHelper::applyPeriod(
                        EmployeeFingerPrintSheet::where('employee_id', $employeeId),
                        $year,
                        $month
                    )->update(['reviewed' => true]);

                    $this->syncImportDetails($employeeId, $month, $year, $details);

                    if ($amount < 0.01) {
                        return [
                            'amount' => 0,
                            'accrual' => null,
                            'skipped' => false,
                        ];
                    }

                    return $this->accrualService->accrueForMonth(
                        $employeeId,
                        $month,
                        $year,
                        $amount,
                        requirePayableAccount: false
                    );
                });

                $updated[] = [
                    'employee_id' => $employeeId,
                    'amount' => $amount,
                    'accrual_id' => $result['accrual']->id ?? null,
                    'details' => $details,
                    'accrual_note' => (! empty($result['skipped'])) ? ($result['reason'] ?? null) : null,
                ];
            } catch (\InvalidArgumentException $e) {
                $failed[] = [
                    'employee_id' => $employeeId,
                    'amount' => $amount,
                    'reason' => $e->getMessage(),
                ];
            } catch (\Throwable $e) {
                $failed[] = [
                    'employee_id' => $employeeId,
                    'amount' => $amount,
                    'reason' => 'فشل التحديث: '.$e->getMessage(),
                ];
            }
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'totals' => [
                'updated_count' => count($updated),
                'skipped_count' => count($skipped),
                'failed_count' => count($failed),
            ],
        ];
    }

    /**
     * @param  array<string, float>  $details
     */
    private function syncImportDetails(int $employeeId, int $month, int $year, array $details): void
    {
        $userId = auth()->id();

        $this->upsertMerit($employeeId, $month, $year, 'حوافز', self::REASON_EXTRA_DAYS, $details['extra_day_value'], $userId);
        $this->upsertMerit($employeeId, $month, $year, 'حوافز', self::REASON_OVERTIME, $details['overtime_value'], $userId);
        $this->upsertMerit($employeeId, $month, $year, 'مكافئات', self::REASON_REWARDS, $details['rewards'], $userId);
        $this->upsertMerit($employeeId, $month, $year, 'بدلات', self::REASON_ALLOWANCES, $details['allowances'], $userId);
        $this->upsertSubtraction($employeeId, $month, $year, 'خصومات', self::REASON_DEDUCTIONS, $details['deductions'], $userId);
        $this->upsertSubtraction($employeeId, $month, $year, 'خصومات', self::REASON_ADVANCE, $details['advance'], $userId);
    }

    private function upsertMerit(
        int $employeeId,
        int $month,
        int $year,
        string $type,
        string $reason,
        float $amount,
        mixed $userId
    ): void {
        EmployeeMerits::query()
            ->where('employee_id', $employeeId)
            ->where('month', $month)
            ->where('year', $year)
            ->where('type', $type)
            ->where('reason', $reason)
            ->delete();

        if ($amount < 0.01) {
            return;
        }

        EmployeeMerits::create([
            'employee_id' => $employeeId,
            'month' => $month,
            'year' => $year,
            'type' => $type,
            'amount' => $amount,
            'reason' => $reason,
            'user_id' => $userId,
            'reviewed' => 1,
        ]);
    }

    private function upsertSubtraction(
        int $employeeId,
        int $month,
        int $year,
        string $type,
        string $reason,
        float $amount,
        mixed $userId
    ): void {
        EmployeeSubtraction::query()
            ->where('employee_id', $employeeId)
            ->where('month', $month)
            ->where('year', $year)
            ->where('type', $type)
            ->where('reason', $reason)
            ->delete();

        if ($amount < 0.01) {
            return;
        }

        EmployeeSubtraction::create([
            'employee_id' => $employeeId,
            'month' => $month,
            'year' => $year,
            'type' => $type,
            'amount' => $amount,
            'reason' => $reason,
            'user_id' => $userId,
            'reviewed' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowHasDetails(array $row): bool
    {
        foreach (['extra_day_value', 'overtime_value', 'rewards', 'allowances', 'deductions', 'advance'] as $key) {
            if (round((float) ($row[$key] ?? 0), 2) >= 0.01) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseSpreadsheet(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if (! $path) {
            throw new \InvalidArgumentException('تعذر قراءة الملف.');
        }

        $spreadsheet = $this->loadSpreadsheet($path);
        $sheet = $spreadsheet->getActiveSheet();
        $matrix = $sheet->toArray(null, true, true, false);

        $normalizedRows = [];
        foreach ($matrix as $zeroIdx => $rawRow) {
            $cells = [];
            foreach (array_values($rawRow) as $i => $value) {
                $cells[$i + 1] = trim((string) ($value ?? ''));
            }
            for ($c = 1; $c <= 50; $c++) {
                $cells[$c] = $cells[$c] ?? '';
            }
            $normalizedRows[] = [
                'excel_row' => $zeroIdx + 1,
                'cells' => $cells,
            ];
        }

        [$headerMap, $dataStartIdx] = $this->resolveHeaderMap($normalizedRows);
        $headerMap = $this->applyKnownLayoutDefaults($headerMap);

        $rows = [];
        for ($i = $dataStartIdx; $i < count($normalizedRows); $i++) {
            $cells = $normalizedRows[$i]['cells'];
            $r = $normalizedRows[$i]['excel_row'];

            if ($this->rowIsEmpty($cells)) {
                continue;
            }

            // صف هيدر إضافي بعد الدمج
            if ($this->detectHeaderMap($cells) !== null || $this->rowLooksLikeHeaderOnly($cells)) {
                continue;
            }

            $code = $this->normalizeCode((string) ($cells[$headerMap['code'] ?? 0] ?? ''));
            $fingerprint = $this->normalizeCode((string) ($cells[$headerMap['fingerprint'] ?? 0] ?? ''));
            $name = trim((string) ($cells[$headerMap['name'] ?? 0] ?? ''));
            $amount = $this->parseAmount((string) ($cells[$headerMap['amount'] ?? 0] ?? '0'));

            if ($code === '' && $fingerprint === '' && $name === '') {
                continue;
            }
            if ($this->looksLikeHeaderLabel($code)
                || $this->looksLikeHeaderLabel($fingerprint)
                || $this->looksLikeHeaderLabel($name)) {
                continue;
            }

            $extraDayValue = $this->cellAmount($cells, $headerMap['extra_day_value'] ?? null);
            $overtimeValue = $this->cellAmount($cells, $headerMap['overtime_value'] ?? null);
            $rewards = $this->cellAmount($cells, $headerMap['rewards'] ?? null)
                + $this->cellAmount($cells, $headerMap['commissions'] ?? null);
            $allowances = $this->cellAmount($cells, $headerMap['transport'] ?? null)
                + $this->cellAmount($cells, $headerMap['meals'] ?? null)
                + $this->cellAmount($cells, $headerMap['allowances'] ?? null);
            $deductions = $this->cellAmount($cells, $headerMap['deductions'] ?? null)
                + $this->cellAmount($cells, $headerMap['delay_deduction'] ?? null)
                + $this->cellAmount($cells, $headerMap['quality_deduction'] ?? null)
                + $this->cellAmount($cells, $headerMap['other_deduction'] ?? null);
            $advance = $this->cellAmount($cells, $headerMap['advance'] ?? null);

            $rows[] = [
                'excel_row' => $r,
                'employee_code' => $code,
                'fingerprint_no' => $fingerprint,
                'employee_name' => $name,
                'amount' => $amount,
                'extra_day_value' => round($extraDayValue, 2),
                'overtime_value' => round($overtimeValue, 2),
                'rewards' => round($rewards, 2),
                'allowances' => round($allowances, 2),
                'deductions' => round($deductions, 2),
                'advance' => round($advance, 2),
            ];
        }

        if ($rows === []) {
            throw new \InvalidArgumentException(
                'لم يتم العثور على صفوف صالحة. الأعمدة المتوقعة: رقم الموظف / رقم البصمة / اسم الموظف + الراتب المستحق أو التفاصيل'
            );
        }

        return $rows;
    }

    /**
     * دمج هيدر متعدد الصفوف (شيت المرتبات غالباً صف تجميعي + صف عناوين).
     *
     * @param  list<array{excel_row:int, cells:array<int,string>}>  $normalizedRows
     * @return array{0: array<string, int|null>, 1: int}
     */
    private function resolveHeaderMap(array $normalizedRows): array
    {
        $merged = $this->emptyHeaderMap();
        $lastHeaderIdx = -1;
        $scanLimit = min(15, count($normalizedRows));

        for ($i = 0; $i < $scanLimit; $i++) {
            $cells = $normalizedRows[$i]['cells'];
            if ($this->rowIsEmpty($cells)) {
                continue;
            }

            $partial = $this->detectHeaderMap($cells, allowPartial: true);
            if ($partial === null) {
                // أول صف بيانات محتمل بعد ما لقينا هوية + مبلغ
                if ($lastHeaderIdx >= 0 && ($merged['amount'] !== null || $this->headerHasDetails($merged))) {
                    break;
                }
                continue;
            }

            foreach ($partial as $key => $col) {
                if ($col !== null && ($merged[$key] ?? null) === null) {
                    $merged[$key] = $col;
                }
            }
            $lastHeaderIdx = $i;

            if ($merged['amount'] !== null
                && ($merged['code'] !== null || $merged['fingerprint'] !== null || $merged['name'] !== null)
                && $this->headerHasDetails($merged)) {
                // اكتمل الهيدر بالتفاصيل
                break;
            }
        }

        if ($merged['amount'] === null
            && $merged['code'] === null
            && $merged['fingerprint'] === null
            && $merged['name'] === null) {
            $merged = [
                'amount' => 1,
                'fingerprint' => 2,
                'code' => 3,
                'name' => 4,
            ] + $this->emptyHeaderMap();
            $lastHeaderIdx = -1;
        }

        return [$merged, $lastHeaderIdx + 1];
    }

    /**
     * @return array<string, null>
     */
    private function emptyHeaderMap(): array
    {
        return [
            'code' => null,
            'fingerprint' => null,
            'name' => null,
            'amount' => null,
            'extra_day_value' => null,
            'overtime_value' => null,
            'rewards' => null,
            'commissions' => null,
            'transport' => null,
            'meals' => null,
            'allowances' => null,
            'deductions' => null,
            'delay_deduction' => null,
            'quality_deduction' => null,
            'other_deduction' => null,
            'advance' => null,
        ];
    }

    /**
     * @param  array<string, int|null>  $map
     */
    private function headerHasDetails(array $map): bool
    {
        foreach ([
            'extra_day_value', 'overtime_value', 'rewards', 'commissions',
            'transport', 'meals', 'allowances', 'deductions',
            'delay_deduction', 'quality_deduction', 'other_deduction', 'advance',
        ] as $key) {
            if (($map[$key] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * تخطيط شيت المرتبات المعروف: A مستحق … J قيمة يوم إضافي … L قيمة ساعة … R سلفة.
     *
     * @param  array<string, int|null>  $map
     * @return array<string, int|null>
     */
    private function applyKnownLayoutDefaults(array $map): array
    {
        $looksLikePayrollSheet = ((int) ($map['amount'] ?? 0) === 1)
            && ((int) ($map['code'] ?? 0) === 3 || (int) ($map['fingerprint'] ?? 0) === 2);

        if (! $looksLikePayrollSheet) {
            return $map;
        }

        $map['extra_day_value'] ??= 10;
        $map['overtime_value'] ??= 12;
        $map['rewards'] ??= 13;
        $map['commissions'] ??= 14;
        $map['transport'] ??= 15;
        $map['meals'] ??= 16;
        $map['advance'] ??= 18;

        return $map;
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function rowLooksLikeHeaderOnly(array $cells): bool
    {
        $nonEmpty = 0;
        $headerish = 0;
        foreach ($cells as $v) {
            $t = trim((string) $v);
            if ($t === '') {
                continue;
            }
            $nonEmpty++;
            if ($this->looksLikeHeaderLabel($t) || $this->normalizeHeader($t) !== '' && ! is_numeric($t)) {
                // نصوص عربية للعناوين بدون رقم موظف
                if (! preg_match('/^\d+(\.0+)?$/', $t)) {
                    $headerish++;
                }
            }
        }

        return $nonEmpty > 0 && $headerish >= max(2, (int) floor($nonEmpty * 0.7));
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function cellAmount(array $cells, ?int $col): float
    {
        if ($col === null || $col <= 0) {
            return 0.0;
        }

        return $this->parseAmount((string) ($cells[$col] ?? '0'));
    }

    private function loadSpreadsheet(string $path): Spreadsheet
    {
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['csv', 'txt'], true)) {
            $raw = (string) file_get_contents($path);
            if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
                $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-16');
            } elseif (! mb_check_encoding($raw, 'UTF-8')) {
                $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1256');
            }
            $tmp = tempnam(sys_get_temp_dir(), 'payimp');
            file_put_contents($tmp, $raw);
            $reader = IOFactory::createReader('Csv');
            $reader->setDelimiter($this->detectCsvDelimiter($raw));
            $reader->setEnclosure('"');
            $reader->setSheetIndex(0);

            return $reader->load($tmp);
        }

        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        if (method_exists($reader, 'setReadFilter')) {
            $reader->setReadFilter(new LimitedExcelReadFilter(maxRow: 20000, maxCol: 50));
        }

        return $reader->load($path);
    }

    private function detectCsvDelimiter(string $utf8): string
    {
        $lines = preg_split("/\r\n|\r|\n/", $utf8) ?: [];
        $first = '';
        foreach ($lines as $ln) {
            if (trim((string) $ln) !== '') {
                $first = (string) $ln;
                break;
            }
        }
        $tabs = substr_count($first, "\t");
        $semi = substr_count($first, ';');
        $comma = substr_count($first, ',');
        if ($tabs >= 1 && $tabs >= $semi && $tabs >= $comma) {
            return "\t";
        }
        if ($semi >= 1 && $semi >= $comma) {
            return ';';
        }

        return ',';
    }

    /**
     * @param  array<int, string>  $cells
     * @return array<string, int|null>|null
     */
    private function detectHeaderMap(array $cells, bool $allowPartial = false): ?array
    {
        $map = $this->emptyHeaderMap();
        $matchedAny = false;

        foreach ($cells as $col => $value) {
            $h = $this->normalizeHeader($value);
            if ($h === '') {
                continue;
            }

            // تجاهل عناوين المجموعات المدمجة
            if ($this->headerMatches($h, ['بيانات الموظف', 'تفاصيل الدخل', 'تفاصيل الخصومات', 'الاستحقاقات', 'الاستقطاعات'])) {
                continue;
            }

            // قيم الأيام/الساعات قبل العناوين العامة
            if ($map['extra_day_value'] === null && $this->headerMatches($h, [
                'قيمه يوم اضافي',
                'قيمة يوم إضافي',
                'قيمة يوم اضافي',
                'قيمه اليوم الاضافي',
            ])) {
                $map['extra_day_value'] = (int) $col;
                $matchedAny = true;
                continue;
            }

            if ($map['overtime_value'] === null && $this->headerMatches($h, [
                'قيمه الساعه الاضافيه',
                'قيمة الساعة الإضافية',
                'قيمة الساعه الاضافيه',
                'قيمة الساعات الإضافية',
                'قيمه الساعات الاضافيه',
            ])) {
                $map['overtime_value'] = (int) $col;
                $matchedAny = true;
                continue;
            }

            if ($map['amount'] === null && $this->headerMatches($h, [
                'الراتب المستحق',
                'صافي الراتب',
                'صافى الراتب',
                'المحصلة',
                'net salary',
                'net_salary',
                'due salary',
            ])) {
                $map['amount'] = (int) $col;
                $matchedAny = true;
                continue;
            }

            if ($map['code'] === null && $this->headerMatches($h, [
                'رقم الموظف',
                'كود الموظف',
                'employee code',
                'employee_code',
            ])) {
                $map['code'] = (int) $col;
                $matchedAny = true;
                continue;
            }

            if ($map['fingerprint'] === null && $this->headerMatches($h, [
                'رقم البصمة',
                'البصمة',
                'fingerprint',
                'acc_no',
                'acc no',
            ])) {
                $map['fingerprint'] = (int) $col;
                $matchedAny = true;
                continue;
            }

            if ($map['name'] === null && $this->headerMatches($h, [
                'اسم الموظف',
                'الاسم',
                'employee name',
                'employee_name',
            ])) {
                $map['name'] = (int) $col;
                $matchedAny = true;
                continue;
            }

            if ($map['rewards'] === null && $this->headerMatches($h, [
                'مكافات',
                'مكافآت',
                'مكافئات',
            ])) {
                $map['rewards'] = (int) $col;
                $matchedAny = true;
                continue;
            }

            if ($map['commissions'] === null && $this->headerMatches($h, [
                'عمولات',
                'عموله',
                'commission',
            ])) {
                $map['commissions'] = (int) $col;
                $matchedAny = true;
                continue;
            }

            if ($map['transport'] === null && $this->headerMatches($h, [
                'بدل مواصلات',
            ])) {
                $map['transport'] = (int) $col;
                $matchedAny = true;
                continue;
            }

            if ($map['meals'] === null && $this->headerMatches($h, [
                'بدل وجبات',
                'بدل وجبه',
            ])) {
                $map['meals'] = (int) $col;
                $matchedAny = true;
                continue;
            }

            if ($map['allowances'] === null && $this->headerMatches($h, [
                'بدلات',
            ])) {
                $map['allowances'] = (int) $col;
                $matchedAny = true;
                continue;
            }

            if ($map['advance'] === null && $this->headerMatches($h, [
                'سلفه',
                'سلفة',
                'سلف',
            ])) {
                $map['advance'] = (int) $col;
                $matchedAny = true;
                continue;
            }

            if ($map['delay_deduction'] === null && $this->headerMatches($h, [
                'خصومات تاخير',
                'خصومات تأخير',
                'خصم التاخير',
                'خصم الغياب والتاخير',
            ])) {
                $map['delay_deduction'] = (int) $col;
                $matchedAny = true;
                continue;
            }

            if ($map['quality_deduction'] === null && $this->headerMatches($h, [
                'خصومات اخطاء',
                'خصومات أخطاء',
                'انتاج / جوده',
                'انتاج / جودة',
            ])) {
                $map['quality_deduction'] = (int) $col;
                $matchedAny = true;
                continue;
            }

            if ($map['other_deduction'] === null && $this->headerMatches($h, [
                'خصومات اخرى',
                'خصومات أخرى',
            ])) {
                $map['other_deduction'] = (int) $col;
                $matchedAny = true;
                continue;
            }

            if ($map['deductions'] === null && $this->headerMatches($h, [
                'خصومات',
                'الخصومات',
            ])) {
                $map['deductions'] = (int) $col;
                $matchedAny = true;
            }
        }

        if (! $matchedAny) {
            return null;
        }

        if ($allowPartial) {
            return $map;
        }

        $hasIdentity = $map['code'] !== null || $map['fingerprint'] !== null || $map['name'] !== null;
        $hasMoney = $map['amount'] !== null
            || $map['extra_day_value'] !== null
            || $map['overtime_value'] !== null
            || $map['deductions'] !== null
            || $map['advance'] !== null
            || $map['rewards'] !== null
            || $map['allowances'] !== null
            || $map['delay_deduction'] !== null;

        if (! $hasIdentity || ! $hasMoney) {
            return null;
        }

        return $map;
    }

    /**
     * @param  list<string>  $candidates
     */
    private function headerMatches(string $normalizedHeader, array $candidates): bool
    {
        foreach ($candidates as $c) {
            $cand = $this->normalizeHeader($c);
            if ($normalizedHeader === $cand) {
                return true;
            }
            if ($cand !== '' && str_contains($normalizedHeader, $cand)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeHeader(string $value): string
    {
        $v = mb_strtolower(trim($value), 'UTF-8');
        $v = str_replace(['أ', 'إ', 'آ'], 'ا', $v);
        $v = str_replace('ة', 'ه', $v);
        $v = str_replace('ى', 'ي', $v);
        $v = preg_replace('/\s+/u', ' ', $v) ?? $v;

        return $v;
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function rowIsEmpty(array $cells): bool
    {
        foreach ($cells as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }

    private function looksLikeHeaderLabel(string $value): bool
    {
        $h = $this->normalizeHeader($value);
        if ($h === '') {
            return false;
        }

        return $this->headerMatches($h, [
            'رقم الموظف',
            'كود الموظف',
            'رقم البصمة',
            'اسم الموظف',
            'الراتب المستحق',
            'صافي الراتب',
            'صافى الراتب',
            'الموظفين',
            'employee',
            'net salary',
            'قيمه يوم اضافي',
            'ساعات اضافيه',
        ]);
    }

    private function normalizeCode(string $value): string
    {
        $v = trim($value);
        if ($v === '') {
            return '';
        }
        if (preg_match('/^(\d+)\.0+$/', $v, $m)) {
            return $m[1];
        }
        if (is_numeric($v)) {
            return (string) (int) round((float) $v);
        }

        return $v;
    }

    /**
     * مطابقة صف الإكسيل بموظف النظام.
     * الاسم الفريد أولاً (خصوصاً من لهم رقم بصمة) حتى لو رقم الموظف في الشيت مسلسل داخلي.
     *
     * @param  array<string, \App\Models\Employee>  $byCode
     * @param  array<string, \App\Models\Employee>  $byAccNo
     * @param  array<string, list<\App\Models\Employee>>  $byName
     * @return array{0: ?\App\Models\Employee, 1: ?string, 2: ?string}
     */
    private function resolveEmployee(
        string $code,
        string $accNo,
        string $name,
        array $byCode,
        array $byAccNo,
        array $byName
    ): array {
        $nameKey = $this->normalizeName($name);
        $nameHits = ($nameKey !== '' && isset($byName[$nameKey])) ? $byName[$nameKey] : [];

        $uniqueName = count($nameHits) === 1 ? $nameHits[0] : null;
        $uniqueFingerprintName = $this->uniqueFingerprintEmployee($nameHits);

        if ($uniqueFingerprintName) {
            return [$uniqueFingerprintName, 'name', null];
        }
        if ($uniqueName) {
            return [$uniqueName, 'name', null];
        }

        if ($code !== '' && isset($byCode[$code])) {
            return [$byCode[$code], 'code', null];
        }
        if ($accNo !== '' && isset($byAccNo[$accNo])) {
            return [$byAccNo[$accNo], 'acc_no', null];
        }

        if (count($nameHits) > 1) {
            return [null, null, 'الاسم مكرر لأكثر من موظف — راجع الكود أو رقم البصمة'];
        }

        $fuzzy = $this->fuzzyMatchUniqueName($nameKey, $byName);
        if ($fuzzy) {
            return [$fuzzy, 'name', null];
        }

        return [null, null, 'الموظف غير موجود في النظام'];
    }

    /**
     * @param  list<\App\Models\Employee>  $employees
     */
    private function uniqueFingerprintEmployee(array $employees): ?Employee
    {
        $hits = [];
        foreach ($employees as $emp) {
            if (trim((string) ($emp->acc_no ?? '')) !== '') {
                $hits[] = $emp;
            }
        }

        return count($hits) === 1 ? $hits[0] : null;
    }

    /**
     * @param  array<string, list<\App\Models\Employee>>  $byName
     */
    private function fuzzyMatchUniqueName(string $nameKey, array $byName): ?Employee
    {
        if ($nameKey === '' || mb_strlen($nameKey, 'UTF-8') < 6) {
            return null;
        }

        $tokens = array_values(array_filter(
            explode(' ', $nameKey),
            static fn (string $t) => mb_strlen($t, 'UTF-8') >= 2
        ));
        if (count($tokens) < 2) {
            return null;
        }

        $hits = [];
        foreach ($byName as $key => $emps) {
            foreach ($tokens as $token) {
                if (! str_contains($key, $token)) {
                    continue 2;
                }
            }
            foreach ($emps as $emp) {
                $hits[(int) $emp->id] = $emp;
            }
        }

        $fingerprintHit = $this->uniqueFingerprintEmployee(array_values($hits));
        if ($fingerprintHit) {
            return $fingerprintHit;
        }

        if (count($hits) === 1) {
            return array_values($hits)[0];
        }

        return null;
    }

    private function normalizeName(string $value): string
    {
        $v = mb_strtolower(trim($value), 'UTF-8');
        $v = str_replace(['أ', 'إ', 'آ'], 'ا', $v);
        $v = str_replace(['ة', 'ۀ'], 'ه', $v);
        $v = str_replace(['ى', 'ئ'], 'ي', $v);
        $v = str_replace('ؤ', 'و', $v);
        $v = str_replace('ـ', '', $v);
        $v = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $v) ?? $v;
        $v = preg_replace('/\s+/u', ' ', $v) ?? $v;
        $v = trim($v);

        $prefixes = ['الاستاذ', 'الاستاذه', 'السيد', 'السيده', 'المهندس', 'المهندسه', 'الدكتور', 'الدكتوره'];
        foreach ($prefixes as $prefix) {
            if (str_starts_with($v, $prefix.' ')) {
                $v = trim(mb_substr($v, mb_strlen($prefix, 'UTF-8'), null, 'UTF-8'));
                break;
            }
        }

        return $v;
    }

    private function parseAmount(string $value): float
    {
        $v = trim($value);
        if ($v === '') {
            return 0.0;
        }
        $negative = false;
        if (str_starts_with($v, '(') && str_ends_with($v, ')')) {
            $negative = true;
            $v = substr($v, 1, -1);
        }
        $v = str_replace([',', ' ', '٬', '،'], '', $v);
        $v = str_replace('%', '', $v);
        if (! is_numeric($v)) {
            return 0.0;
        }
        $n = (float) $v;

        return round($negative ? -abs($n) : $n, 2);
    }
}
