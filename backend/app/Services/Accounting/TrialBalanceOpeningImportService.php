<?php

namespace App\Services\Accounting;

use App\Models\AccountEntry;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\TreeAccount;
use App\Models\User;
use App\Services\Items\LimitedExcelReadFilter;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * استيراد أرصدة افتتاحية من ميزان مراجعة Excel ومطابقتها بكود الحساب.
 */
class TrialBalanceOpeningImportService
{
    public const BATCH_PREFIX = 'OPENING-IMPORT-';

    public function __construct(
        private AccountingService $accountingService,
        private BudgetReviewService $budgetService,
        private OperationalLinkedBalanceSyncService $operationalSync
    ) {}

    /**
     * @return array{
     *   matched: list<array<string, mixed>>,
     *   missing: list<array<string, mixed>>,
     *   skipped: list<array<string, mixed>>,
     *   totals: array{debit: float, credit: float, difference: float, matched_count: int, missing_count: int}
     * }
     */
    public function preview(UploadedFile $file): array
    {
        $rows = $this->parseSpreadsheet($file);
        $codes = array_values(array_unique(array_filter(array_map(
            fn (array $r) => $r['account_code'],
            $rows
        ))));

        $accountsByCode = TreeAccount::query()
            ->whereIn('code', $codes)
            ->get(['id', 'code', 'name', 'type', 'level', 'parent_id'])
            ->keyBy(fn (TreeAccount $a) => (string) $a->code);

        $matched = [];
        $missing = [];
        $skipped = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($rows as $row) {
            $code = $row['account_code'];
            $debit = $row['debit'];
            $credit = $row['credit'];
            $name = trim((string) ($row['account_name'] ?? ''));

            // حسابات بدون AccountID: تظهر للإضافة تحت أب أو الربط بحساب موجود
            if ($code === '') {
                if ($name === '') {
                    $skipped[] = array_merge($row, ['reason' => 'كود واسم الحساب فارغان']);
                    continue;
                }

                $missing[] = [
                    'excel_row' => $row['excel_row'],
                    'account_code' => '',
                    'account_name_excel' => $name,
                    'debit' => $debit,
                    'credit' => $credit,
                    'target_net' => round($debit - $credit, 2),
                    'no_account_id' => true,
                ];
                continue;
            }

            if (abs($debit) < 0.01 && abs($credit) < 0.01) {
                $skipped[] = array_merge($row, ['reason' => 'رصيد صفر']);
                continue;
            }

            if ($debit > 0.009 && $credit > 0.009) {
                $skipped[] = array_merge($row, ['reason' => 'لا يمكن أن يكون للحساب مدين ودائن معاً في نفس الصف']);
                continue;
            }

            $account = $accountsByCode->get($code);
            $payload = [
                'excel_row' => $row['excel_row'],
                'account_code' => $code,
                'account_name_excel' => $row['account_name'],
                'debit' => $debit,
                'credit' => $credit,
                'target_net' => round($debit - $credit, 2),
                'no_account_id' => false,
            ];

            if (! $account) {
                $missing[] = $payload;
                continue;
            }

            $payload['tree_account_id'] = (int) $account->id;
            $payload['account_name_system'] = (string) $account->name;
            $payload['account_type'] = (string) $account->type;
            $payload['level'] = (int) $account->level;
            $payload['name_mismatch'] = $this->normalizeName($row['account_name']) !== ''
                && $this->normalizeName($row['account_name']) !== $this->normalizeName((string) $account->name);

            $matched[] = $payload;
            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        return [
            'matched' => $matched,
            'missing' => $missing,
            'skipped' => $skipped,
            'totals' => [
                'debit' => round($totalDebit, 2),
                'credit' => round($totalCredit, 2),
                'difference' => round($totalDebit - $totalCredit, 2),
                'matched_count' => count($matched),
                'missing_count' => count($missing),
            ],
        ];
    }

    /**
     * إنشاء حساب ناقص بنفس كود الإكسيل، أو توليد كود تلقائي تحت الحساب الأب إن كان الكود فارغاً.
     *
     * @return array{account: TreeAccount, line: array<string, mixed>}
     */
    public function createMissingAccount(
        ?string $code,
        string $name,
        string $type,
        ?int $parentId,
        float $debit = 0.0,
        float $credit = 0.0
    ): array {
        $code = trim((string) $code);
        $name = trim($name);

        if ($name === '') {
            throw new \InvalidArgumentException('اسم الحساب مطلوب.');
        }

        if (! in_array($type, ['asset', 'liability', 'equity', 'revenue', 'expense', 'settlement'], true)) {
            throw new \InvalidArgumentException('نوع الحساب غير صالح.');
        }

        if ($code === '' && ! $parentId) {
            throw new \InvalidArgumentException('الحساب بدون كود يتطلب اختيار حساب أب لتوليد الكود تحته.');
        }

        if ($code !== '' && TreeAccount::withTrashed()->where('code', $code)->exists()) {
            throw new \InvalidArgumentException('كود الحساب موجود بالفعل في النظام: '.$code);
        }

        if (TreeAccount::nameAlreadyUsed($name)) {
            throw new \InvalidArgumentException('اسم الحساب مستخدم مسبقاً.');
        }

        $account = DB::transaction(function () use (&$code, $name, $type, $parentId) {
            $level = 1;
            $resolvedParentId = null;

            if ($parentId) {
                $parent = TreeAccount::query()->lockForUpdate()->find($parentId);
                if (! $parent) {
                    throw new \InvalidArgumentException('الحساب الأب غير موجود.');
                }
                if ($parent->type !== $type) {
                    throw new \InvalidArgumentException('نوع الحساب يجب أن يطابق نوع الحساب الأب.');
                }
                $resolvedParentId = (int) $parent->id;
                $level = ((int) $parent->level) + 1;

                if ($code === '') {
                    $lastChild = TreeAccount::queryLastChildUnderParentLocked($parent);
                    $resolved = TreeAccount::resolveNextChildCodeAndLevel($parent, $lastChild);
                    $code = $resolved['code'];
                    while (TreeAccount::withTrashed()->where('code', $code)->exists()) {
                        $code = (string) ((int) $code + 1);
                    }
                    $level = (int) $resolved['level'];
                }
            }

            return TreeAccount::create([
                'code' => $code,
                'name' => $name,
                'parent_id' => $resolvedParentId,
                'type' => $type,
                'level' => $level,
                'balance' => 0,
                'debit_balance' => 0,
                'credit_balance' => 0,
                'is_trading_account' => false,
            ]);
        });

        return [
            'account' => $account->fresh(),
            'line' => [
                'account_code' => (string) $account->code,
                'account_name_excel' => $name,
                'account_name_system' => (string) $account->name,
                'tree_account_id' => (int) $account->id,
                'account_type' => (string) $account->type,
                'level' => (int) $account->level,
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
                'target_net' => round($debit - $credit, 2),
                'name_mismatch' => false,
            ],
        ];
    }

    /**
     * ترحيل الأرصدة الافتتاحية بتاريخ محدد (بداية الرصيد).
     *
     * @param  list<array{tree_account_id:int, debit?:float, credit?:float, target_net?:float}>  $lines
     * @return array{daily_entry: DailyEntry, posted_count: int, batch_code: string, opening_date: string, entry_created_at: string}
     */
    public function apply(
        array $lines,
        TreeAccount $counterAccount,
        string $openingDateYmd,
        int $userId,
        ?string $reason = null
    ): array {
        if ($lines === []) {
            throw new \InvalidArgumentException('لا توجد بنود للترحيل.');
        }

        $openingDate = Carbon::parse($openingDateYmd)->format('Y-m-d');
        // ميزان المراجعة يعتبر الافتتاح = created_at قبل date_from 00:00:00
        // لذلك نضع القيد لحظياً قبل منتصف ليل تاريخ البداية ليظهر كرصيد أول المدة.
        $entryAt = Carbon::parse($openingDate)->startOfDay()->subSecond();
        $batchCode = self::BATCH_PREFIX.$openingDate;

        return DB::transaction(function () use ($lines, $counterAccount, $openingDate, $entryAt, $batchCode, $userId, $reason) {
            $counterAccount->refresh();
            $counterId = (int) $counterAccount->id;

            $this->deleteExistingBatch($batchCode);

            $posted = [];
            $budgetChunks = [];
            $user = User::find($userId);
            $userLabel = $user?->name ?? $user?->email ?? ('#'.$userId);

            foreach ($lines as $line) {
                $accountId = (int) ($line['tree_account_id'] ?? 0);
                if ($accountId <= 0) {
                    throw new \InvalidArgumentException('tree_account_id مطلوب لكل بند.');
                }
                if ($accountId === $counterId) {
                    throw new \InvalidArgumentException('لا يمكن استخدام الحساب المقابل ضمن بنود الاستيراد.');
                }

                $account = TreeAccount::find($accountId);
                if (! $account) {
                    throw new \InvalidArgumentException('حساب غير موجود: '.$accountId);
                }

                $debit = round((float) ($line['debit'] ?? 0), 2);
                $credit = round((float) ($line['credit'] ?? 0), 2);
                if (array_key_exists('target_net', $line) && ! array_key_exists('debit', $line) && ! array_key_exists('credit', $line)) {
                    $net = round((float) $line['target_net'], 2);
                    $debit = $net > 0 ? $net : 0.0;
                    $credit = $net < 0 ? abs($net) : 0.0;
                }

                if (abs($debit) < 0.01 && abs($credit) < 0.01) {
                    continue;
                }
                if ($debit > 0.009 && $credit > 0.009) {
                    throw new \InvalidArgumentException('الحساب '.$account->code.' له مدين ودائن معاً.');
                }

                $targetNet = round($debit - $credit, 2);
                $abs = abs($targetNet);
                if ($targetNet >= 0) {
                    $adjDebit = $abs;
                    $adjCredit = 0.0;
                    $cntDebit = 0.0;
                    $cntCredit = $abs;
                } else {
                    $adjDebit = 0.0;
                    $adjCredit = $abs;
                    $cntDebit = $abs;
                    $cntCredit = 0.0;
                }

                $lineDescription = sprintf(
                    'استيراد رصيد افتتاحي بتاريخ %s — بواسطة «%s» — [%s] %s — مدين: %s — دائن: %s — المقابل: [%s] %s%s',
                    $openingDate,
                    $userLabel,
                    $account->code,
                    $account->name,
                    number_format($adjDebit, 2, '.', ''),
                    number_format($adjCredit, 2, '.', ''),
                    $counterAccount->code,
                    $counterAccount->name,
                    ($reason ? ' — '.$reason : '')
                );

                $budgetChunks[] = ['tree_account_id' => $accountId, 'debit' => $adjDebit, 'credit' => $adjCredit];
                $budgetChunks[] = ['tree_account_id' => $counterId, 'debit' => $cntDebit, 'credit' => $cntCredit];

                $posted[] = [
                    'account_id' => $accountId,
                    'adj_debit' => $adjDebit,
                    'adj_credit' => $adjCredit,
                    'cnt_debit' => $cntDebit,
                    'cnt_credit' => $cntCredit,
                    'description' => $lineDescription,
                ];
            }

            if ($posted === []) {
                throw new \InvalidArgumentException('لا توجد أرصدة غير صفرية للترحيل.');
            }

            $budgetResult = $this->budgetService->checkBudget($budgetChunks, $openingDate);
            if (! $budgetResult['valid']) {
                throw new \RuntimeException($budgetResult['message']);
            }

            $headerDescription = $reason
                ? $reason
                : 'استيراد أرصدة افتتاحية من ميزان المراجعة — بتاريخ '.$openingDate.' ('.count($posted).' حساب)';

            $dailyEntry = DailyEntry::create([
                'date' => $openingDate,
                'entry_number' => DailyEntry::getNextEntryNumber(),
                'description' => $headerDescription,
                'user_id' => $userId,
            ]);

            $touchedIds = [$counterId];

            foreach ($posted as $row) {
                DailyEntryItem::create([
                    'daily_entry_id' => $dailyEntry->id,
                    'account_id' => $row['account_id'],
                    'debit' => $row['adj_debit'],
                    'credit' => $row['adj_credit'],
                    'notes' => $row['description'],
                ]);
                DailyEntryItem::create([
                    'daily_entry_id' => $dailyEntry->id,
                    'account_id' => $counterId,
                    'debit' => $row['cnt_debit'],
                    'credit' => $row['cnt_credit'],
                    'notes' => $row['description'],
                ]);

                AccountEntry::create([
                    'tree_account_id' => $row['account_id'],
                    'debit' => $row['adj_debit'],
                    'credit' => $row['adj_credit'],
                    'description' => $row['description'],
                    'daily_entry_id' => $dailyEntry->id,
                    'entry_batch_code' => $batchCode,
                    'created_at' => $entryAt,
                    'updated_at' => $entryAt,
                ]);
                AccountEntry::create([
                    'tree_account_id' => $counterId,
                    'debit' => $row['cnt_debit'],
                    'credit' => $row['cnt_credit'],
                    'description' => $row['description'],
                    'daily_entry_id' => $dailyEntry->id,
                    'entry_batch_code' => $batchCode,
                    'created_at' => $entryAt,
                    'updated_at' => $entryAt,
                ]);

                $touchedIds[] = $row['account_id'];
            }

            $uniqueTouched = array_values(array_unique($touchedIds));
            foreach ($uniqueTouched as $aid) {
                $this->accountingService->updateAccountHierarchyBalances((int) $aid);
            }

            // مزامنة أرصدة الخزائن/البنوك/الموردين/العملاء/الشحن مع GL بعد الاستيراد
            $operationalSync = $this->operationalSync->syncFromGl(
                $uniqueTouched,
                $reason ?: ('مزامنة بعد استيراد أرصدة افتتاحية '.$openingDate)
            );

            $dailyEntry->load(['items.account', 'user']);

            return [
                'daily_entry' => $dailyEntry,
                'posted_count' => count($posted),
                'batch_code' => $batchCode,
                'opening_date' => $openingDate,
                'entry_created_at' => $entryAt->format('Y-m-d H:i:s'),
                'operational_sync' => $operationalSync,
            ];
        });
    }

    private function deleteExistingBatch(string $batchCode): void
    {
        $oldEntries = AccountEntry::where('entry_batch_code', $batchCode)->get();
        if ($oldEntries->isEmpty()) {
            return;
        }

        $oldDailyIds = $oldEntries->pluck('daily_entry_id')->filter()->unique()->values()->all();
        AccountEntry::where('entry_batch_code', $batchCode)->delete();
        if ($oldDailyIds) {
            DailyEntryItem::whereIn('daily_entry_id', $oldDailyIds)->delete();
            DailyEntry::whereIn('id', $oldDailyIds)->delete();
        }
    }

    /**
     * @return list<array{excel_row:int, account_code:string, account_name:string, debit:float, credit:float}>
     */
    private function parseSpreadsheet(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if (! $path) {
            throw new \InvalidArgumentException('تعذر قراءة الملف.');
        }

        $spreadsheet = $this->loadSpreadsheet($path);
        $matrix = $this->extractBestSheetMatrix($spreadsheet);

        $headerMap = null;
        $pendingBeforeHeader = [];
        $rows = [];

        foreach ($matrix as $zeroIdx => $rawRow) {
            $r = $zeroIdx + 1;
            $cells = $this->rowToIndexedCells($rawRow);

            if ($this->rowIsEmpty($cells)) {
                continue;
            }

            if ($headerMap === null) {
                $detected = $this->detectHeaderMap($cells);
                if ($detected !== null) {
                    $headerMap = $detected;
                    continue;
                }
                $pendingBeforeHeader[] = ['excel_row' => $r, 'cells' => $cells];
                continue;
            }

            $parsed = $this->parseDataRow($cells, $headerMap, $r);
            if ($parsed !== null) {
                $rows[] = $parsed;
            }
        }

        if ($headerMap === null) {
            // بدون هيدر: AccountID | AccountName | PrevCR | PrevDB
            $headerMap = [
                'code' => 1,
                'name' => 2,
                'credit' => 3,
                'debit' => 4,
                'debit_alt' => null,
                'credit_alt' => null,
            ];
            foreach ($pendingBeforeHeader as $pending) {
                $parsed = $this->parseDataRow($pending['cells'], $headerMap, $pending['excel_row']);
                if ($parsed !== null) {
                    $rows[] = $parsed;
                }
            }
        }

        if ($rows === []) {
            throw new \InvalidArgumentException(
                'لم يتم العثور على صفوف صالحة في ملف الإكسيل. الصيغ المدعومة: تصدير ميزان المراجعة، أو AccountID و AccountName و PrevCRBalance و PrevDBBalance'
            );
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $rawRow
     * @return array<int, string>
     */
    private function rowToIndexedCells(array $rawRow): array
    {
        $cells = [];
        foreach (array_values($rawRow) as $i => $value) {
            $cells[$i + 1] = trim((string) ($value ?? ''));
        }
        for ($c = 1; $c <= 4; $c++) {
            $cells[$c] = $cells[$c] ?? '';
        }

        return $cells;
    }

    /**
     * @param  array<int, string>  $cells
     * @param  array{code:int, name:int, credit:int, debit:int, debit_alt:?int, credit_alt:?int}  $headerMap
     * @return array{excel_row:int, account_code:string, account_name:string, debit:float, credit:float}|null
     */
    private function parseDataRow(array $cells, array $headerMap, int $excelRow): ?array
    {
        $code = $this->normalizeCode((string) ($cells[$headerMap['code']] ?? ''));
        $name = (string) ($cells[$headerMap['name']] ?? '');
        $debit = $this->parseAmount((string) ($cells[$headerMap['debit']] ?? '0'));
        $credit = $this->parseAmount((string) ($cells[$headerMap['credit']] ?? '0'));

        if ($code === '' && $name === '') {
            return null;
        }
        if ($this->looksLikeHeaderLabel($code) || $this->looksLikeHeaderLabel($name)) {
            return null;
        }

        return [
            'excel_row' => $excelRow,
            'account_code' => $code,
            'account_name' => $name,
            'debit' => $debit,
            'credit' => $credit,
        ];
    }

    /**
     * يختار أفضل ورقة تحتوي هيدر ميزان المراجعة، ويقرأ القيم بأمان حتى مع جداول Excel التالفة.
     *
     * @return list<array<int, mixed>>
     */
    private function extractBestSheetMatrix(Spreadsheet $spreadsheet): array
    {
        $best = null;
        $bestScore = -1;

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $matrix = $this->sheetToSafeMatrix($sheet);
            if ($matrix === []) {
                continue;
            }

            $score = $this->scoreTrialBalanceSheet($matrix);
            $title = (string) $sheet->getTitle();
            if (mb_stripos($title, 'بعد') !== false || mb_stripos($title, 'after') !== false) {
                $score += 50;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $matrix;
            }
        }

        if ($best === null || $bestScore < 0) {
            throw new \InvalidArgumentException('ملف الإكسيل فارغ أو لا يحتوي أوراقاً قابلة للقراءة.');
        }

        // لو مفيش هيدر واضح لكن فيه صفوف، نكمّل؛ وإلا نبلّغ
        if ($bestScore < 50) {
            // جرّب أيضاً toArray كاحتياطي لبعض الملفات
            foreach ($spreadsheet->getAllSheets() as $sheet) {
                try {
                    $matrix = $sheet->toArray(null, false, false, false);
                } catch (\Throwable $e) {
                    continue;
                }
                $score = $this->scoreTrialBalanceSheet($matrix);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $matrix;
                }
            }
        }

        return $best ?? [];
    }

    /**
     * قراءة صفوف الورقة خلية بخلية مع تجاهل صيغ Structured Reference التالفة.
     *
     * @return list<array<int, mixed>>
     */
    private function sheetToSafeMatrix(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        $highestRow = (int) $sheet->getHighestDataRow();
        $highestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(
            $sheet->getHighestDataColumn() ?: 'A'
        );
        $highestCol = max(10, min($highestCol, 20));
        $highestRow = max(1, min($highestRow, 20000));

        $matrix = [];
        for ($r = 1; $r <= $highestRow; $r++) {
            $row = [];
            $any = false;
            for ($c = 1; $c <= $highestCol; $c++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                $cell = $sheet->getCell($colLetter.$r);
                $value = $this->safeCellValue($cell);
                if ($value !== null && $value !== '') {
                    $any = true;
                }
                $row[] = $value;
            }
            // لا نسقط الصفوف الفارغة هنا؛ parseSpreadsheet يتجاهلها
            if ($any || $r <= 5) {
                $matrix[] = $row;
            } else {
                $matrix[] = $row;
            }
        }

        return $matrix;
    }

    private function safeCellValue(\PhpOffice\PhpSpreadsheet\Cell\Cell $cell): mixed
    {
        try {
            $value = $cell->getCalculatedValue();
        } catch (\Throwable $e) {
            $value = null;
            try {
                if (method_exists($cell, 'getOldCalculatedValue')) {
                    $value = $cell->getOldCalculatedValue();
                }
            } catch (\Throwable $ignored) {
                $value = null;
            }
            if ($value === null || $value === '') {
                $raw = $cell->getValue();
                // تجاهل الصيغ التالفة مثل =Table2[...]
                if (is_string($raw) && str_starts_with(ltrim($raw), '=')) {
                    $value = '';
                } else {
                    $value = $raw;
                }
            }
        }

        if (is_string($value) && str_starts_with(ltrim($value), '=')) {
            try {
                $old = method_exists($cell, 'getOldCalculatedValue') ? $cell->getOldCalculatedValue() : null;
                $value = $old ?? '';
            } catch (\Throwable $e) {
                $value = '';
            }
        }

        if (is_float($value) || is_int($value)) {
            // أكواد الحسابات غالباً أعداد صحيحة
            if (abs($value - round($value)) < 0.00001 && abs($value) < 1e12) {
                return (string) (int) round($value);
            }

            return $value;
        }

        return $value;
    }

    /**
     * @param  list<array<int, mixed>>  $matrix
     */
    private function scoreTrialBalanceSheet(array $matrix): int
    {
        $score = 0;
        $scanLimit = min(30, count($matrix));
        for ($i = 0; $i < $scanLimit; $i++) {
            $cells = [];
            foreach (array_values($matrix[$i] ?? []) as $idx => $value) {
                $cells[$idx + 1] = trim((string) ($value ?? ''));
            }
            if ($this->detectHeaderMap($cells) !== null) {
                $score += 100;
                break;
            }
        }

        // ترجيح الأوراق التي فيها أكواد رقمية في العمود الأول
        $numericCodes = 0;
        foreach (array_slice($matrix, 0, 50) as $row) {
            $code = trim((string) (($row[0] ?? '')));
            if ($code !== '' && preg_match('/^\d+(\.0+)?$/', $code)) {
                $numericCodes++;
            }
        }
        $score += $numericCodes;

        return $score;
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
            $tmp = tempnam(sys_get_temp_dir(), 'tbimp');
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
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }
        if (method_exists($reader, 'setIncludeCharts')) {
            $reader->setIncludeCharts(false);
        }
        if (method_exists($reader, 'setReadFilter')) {
            $reader->setReadFilter(new LimitedExcelReadFilter(maxRow: 20000, maxCol: 20));
        }

        try {
            return $reader->load($path);
        } catch (\Throwable $e) {
            // بعض ملفات Excel المنسوخة تحتوي جداول Structured Reference تالفة؛ نعيد المحاولة بأبسط إعداد
            $fallback = IOFactory::createReaderForFile($path);
            if (method_exists($fallback, 'setReadDataOnly')) {
                $fallback->setReadDataOnly(true);
            }
            if (method_exists($fallback, 'setIncludeCharts')) {
                $fallback->setIncludeCharts(false);
            }

            try {
                return $fallback->load($path);
            } catch (\Throwable $inner) {
                throw new \InvalidArgumentException(
                    'تعذر قراءة ملف الإكسيل بسبب صيغ/جداول تالفة داخله. احفظ نسخة كـ CSV أو Excel عادي بدون جداول (Table) ثم أعد المحاولة. التفاصيل: '.$inner->getMessage()
                );
            }
        }
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
     * @return array{code:int, name:int, credit:int, debit:int, debit_alt:?int, credit_alt:?int}|null
     */
    private function detectHeaderMap(array $cells): ?array
    {
        $found = [
            'code' => null,
            'name' => null,
            'debit_opening' => null,
            'credit_opening' => null,
            'debit_closing' => null,
            'credit_closing' => null,
            'debit_generic' => null,
            'credit_generic' => null,
        ];

        foreach ($cells as $col => $value) {
            $role = $this->classifyHeaderKey($this->normalizeHeader($value));
            if ($role === null || $role === 'ignore') {
                continue;
            }
            if ($found[$role] === null) {
                $found[$role] = (int) $col;
            }
        }

        $debit = $found['debit_opening'] ?? $found['debit_closing'] ?? $found['debit_generic'];
        $credit = $found['credit_opening'] ?? $found['credit_closing'] ?? $found['credit_generic'];
        $code = $found['code'];

        if (! $code || ! $debit || ! $credit) {
            return null;
        }

        $name = $found['name'] ?: ($code + 1);
        $usingOpening = $found['debit_opening'] !== null || $found['credit_opening'] !== null;
        $debitAlt = $usingOpening ? $found['debit_closing'] : null;
        $creditAlt = $usingOpening ? $found['credit_closing'] : null;

        return [
            'code' => (int) $code,
            'name' => (int) $name,
            'credit' => (int) $credit,
            'debit' => (int) $debit,
            'debit_alt' => $debitAlt ? (int) $debitAlt : null,
            'credit_alt' => $creditAlt ? (int) $creditAlt : null,
        ];
    }

    private function classifyHeaderKey(string $key): ?string
    {
        if ($key === '') {
            return null;
        }

        if (in_array($key, [
            'نوع', 'النوع', 'type', 'accounttype',
            'مستوي', 'المستوي', 'level',
        ], true)) {
            return 'ignore';
        }
        if (str_contains($key, 'حركه')) {
            return 'ignore';
        }

        if (in_array($key, [
            'accountid', 'account_id', 'code', 'accountcode',
            'كود', 'كودالحساب', 'رقمالحساب',
        ], true)) {
            return 'code';
        }
        if (in_array($key, [
            'accountname', 'account_name', 'name',
            'اسم', 'اسمالحساب',
        ], true)) {
            return 'name';
        }

        $isDebit = str_contains($key, 'مدين')
            || str_contains($key, 'debit')
            || str_contains($key, 'prevdb')
            || in_array($key, ['db', 'dr'], true);
        $isCredit = str_contains($key, 'دائن')
            || str_contains($key, 'credit')
            || str_contains($key, 'prevcr')
            || $key === 'cr';

        $isOpening = str_contains($key, 'اولالمده')
            || str_contains($key, 'opening')
            || str_contains($key, 'prev');
        $isClosing = str_contains($key, 'اخرالمده')
            || str_contains($key, 'closing');

        if ($isOpening && $isDebit) {
            return 'debit_opening';
        }
        if ($isOpening && $isCredit) {
            return 'credit_opening';
        }
        if ($isClosing && $isDebit) {
            return 'debit_closing';
        }
        if ($isClosing && $isCredit) {
            return 'credit_closing';
        }
        if ($isDebit) {
            return 'debit_generic';
        }
        if ($isCredit) {
            return 'credit_generic';
        }

        return null;
    }

    private function normalizeHeader(string $value): string
    {
        $v = mb_strtolower(trim($value));
        $v = str_replace(['أ', 'إ', 'آ', 'ى', 'ة'], ['ا', 'ا', 'ا', 'ي', 'ه'], $v);
        $v = str_replace([' ', '_', '-', '/', '\\'], '', $v);

        return $v;
    }

    private function looksLikeHeaderLabel(string $value): bool
    {
        $key = $this->normalizeHeader($value);

        return in_array($key, [
            'accountid', 'accountname', 'prevcrbalance', 'prevdbbalance',
            'كودالحساب', 'اسمالحساب', 'مدين', 'دائن', 'الاجمالي',
            'ميزانالمراجعه', 'نوعالحساب', 'المستوي',
        ], true) || str_contains($key, 'اولالمده') || str_contains($key, 'اخرالمده');
    }

    private function normalizeCode(string $code): string
    {
        $code = trim($code);
        if ($code === '') {
            return '';
        }
        // إزالة .0 من أكواد Excel الرقمية
        if (preg_match('/^\d+\.0+$/', $code)) {
            $code = (string) ((int) $code);
        }

        return $code;
    }

    private function parseAmount(string $raw): float
    {
        $v = trim($raw);
        if ($v === '' || $v === '-' || $v === '—') {
            return 0.0;
        }
        $negative = false;
        if (preg_match('/^\((.*)\)$/', $v, $m)) {
            $negative = true;
            $v = $m[1];
        }
        $v = str_replace([',', ' ', '٫'], ['', '', '.'], $v);
        $v = preg_replace('/[^\d.\-]/', '', $v) ?? '0';
        $num = (float) $v;
        if ($negative) {
            $num = -abs($num);
        }

        return round(abs($num), 2);
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function rowIsEmpty(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        return mb_strtolower($name);
    }
}
