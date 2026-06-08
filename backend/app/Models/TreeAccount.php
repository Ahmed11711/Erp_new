<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;

class TreeAccount extends Model
{
    use HasFactory;

    /** أنواع الشجرة: asset, liability, equity, revenue, expense, settlement (تسوية — غالباً طبيعتها دائنة للعرض مثل الخصوم) */

    protected $guarded = [];

    protected $casts = [
        'balance' => 'decimal:2',
        'debit_balance' => 'decimal:2',
        'credit_balance' => 'decimal:2',
        'is_trading_account' => 'boolean',
        'detail_type' => 'string',
    ];

    /**
     * يُستدعى مباشرةً قبل INSERT — أضمن من الأحداث لو تعطّل أو تُستثنى في بعض الإصدارات/الكاش.
     */
    protected function performInsert(Builder $query)
    {
        if (!$this->exists) {
            $this->ensureCodeAndLevelForNewInsert();
        }

        return parent::performInsert($query);
    }

    private function ensureCodeAndLevelForNewInsert(): void
    {
        $code = trim((string) ($this->getAttribute('code') ?? ''));
        if ($code !== '') {
            return;
        }

        if ($this->parent_id) {
            $parent = static::query()->whereKey($this->parent_id)->lockForUpdate()->first();
            if (!$parent) {
                throw new \RuntimeException('الحساب الأب غير موجود');
            }
            $lastChild = static::queryLastChildUnderParentLocked($parent);
            $resolved = static::resolveNextChildCodeAndLevel($parent, $lastChild);
            $code = $resolved['code'];

            while (static::where('code', $code)->exists()) {
                $code = (string) ((int) $code + 1);
            }

            $this->setAttribute('code', $code);
            $this->setAttribute('level', $resolved['level']);

            return;
        }

        $lastRootQuery = static::query()->whereNull('parent_id')->lockForUpdate();
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            $lastRoot = $lastRootQuery->orderByRaw('CAST(code AS UNSIGNED) DESC')->first();
        } elseif ($driver === 'sqlite') {
            $lastRoot = $lastRootQuery->orderByRaw('CAST(code AS INTEGER) DESC')->first();
        } else {
            $lastRoot = $lastRootQuery->orderByDesc('code')->first();
        }
        $next = $lastRoot
            ? ((int) preg_replace('/\D/', '', (string) $lastRoot->code) + 1)
            : 1;
        $this->setAttribute('code', (string) $next);
        $lvl = $this->getAttribute('level');
        if ($lvl === null || (int) $lvl === 0) {
            $this->setAttribute('level', 1);
        }
    }

    /**
     * آخر ابن تحت الأب بترتيب عددي للكود (مع قفل الصفوف داخل المعاملة الحالية).
     */
    public static function queryLastChildUnderParentLocked(self $parent): ?self
    {
        $q = static::query()->where('parent_id', $parent->id)->lockForUpdate();
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            return $q->orderByRaw('CAST(code AS UNSIGNED) DESC')->first();
        }
        if ($driver === 'sqlite') {
            return $q->orderByRaw('CAST(code AS INTEGER) DESC')->first();
        }

        return $q->orderByDesc('code')->first();
    }

    /**
     * كود ومستوى الابن لأي عمق (فرع تحت فرع).
     */
    public static function resolveNextChildCodeAndLevel(self $parent, ?self $lastChild): array
    {
        $parentLevel = max(1, (int) ($parent->level ?? 0));
        $childLevel = $parentLevel + 1;

        if ($lastChild !== null) {
            $n = (int) preg_replace('/\D/', '', (string) $lastChild->code);

            return [
                'code' => (string) ($n + 1),
                'level' => $childLevel,
            ];
        }

        $raw = trim((string) $parent->code);
        $digits = (int) preg_replace('/\D/', '', $raw);

        if ($parentLevel === 2 && strlen($raw) === 2 && ctype_digit($raw) && (int) $raw < 100) {
            return [
                'code' => (string) ((int) ($raw[0] . '0' . $raw[1])),
                'level' => $childLevel,
            ];
        }

        if ($digits < 1) {
            $digits = (int) $parent->id;
        }

        return [
            'code' => (string) ($digits * 10 + 1),
            'level' => $childLevel,
        ];
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function mainAccount()
    {
        return $this->belongsTo(self::class, 'main_account_id');
    }

    public function accountEntries()
    {
        return $this->hasMany(AccountEntry::class, 'tree_account_id');
    }

    public function safes()
    {
        return $this->hasMany(Safe::class, 'account_id');
    }

    /**
     * Whether another tree account already uses this display name (trimmed).
     */
    public static function nameAlreadyUsed(string $name, ?int $exceptId = null): bool
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return false;
        }
        $q = static::query()->whereRaw('TRIM(name) = ?', [$trimmed]);
        if ($exceptId !== null) {
            $q->where('id', '!=', $exceptId);
        }

        return $q->exists();
    }

    /**
     * Total balance = debit - credit (مطابق لأفضل أنظمة المحاسبة)
     */
    public function getTotalBalanceAttribute()
    {
        return (float) ($this->debit_balance ?? 0) - (float) ($this->credit_balance ?? 0);
    }

    /**
     * Prefer a leaf revenue account so postings appear in reports that aggregate leaf accounts only.
     */
    public static function resolveSalesRevenueAccount(): ?self
    {
        $acc = static::where('detail_type', 'sales')->whereDoesntHave('children')->first();
        if ($acc) {
            return $acc;
        }

        return static::where('type', 'revenue')
            ->where(function ($q) {
                $q->where('name', 'like', '%مبيعات%')
                    ->orWhere('name_en', 'like', '%sales%');
            })
            ->whereDoesntHave('children')
            ->orderByRaw('LENGTH(code) DESC')
            ->orderBy('code')
            ->first();
    }

    /**
     * مصروف شحن مشتريات (Freight-in) — لا يُدمج في المخزون في القيد المحاسبي.
     * detail_type المقترح: freight_in
     */
    public static function resolveFreightInExpenseAccount(): ?self
    {
        $acc = static::where('detail_type', 'freight_in')->whereDoesntHave('children')->first();
        if ($acc) {
            return $acc;
        }

        $withDetail = static::where('detail_type', 'freight_in')->orderBy('id')->first();
        if ($withDetail) {
            return static::firstLeafUnderAccount($withDetail);
        }

        return static::where('type', 'expense')
            ->where(function ($q) {
                $q->where('name', 'like', '%شحن مشتريات%')
                    ->orWhere('name', 'like', '%شحن توريد%')
                    ->orWhere('name_en', 'like', '%freight in%')
                    ->orWhere('name_en', 'like', '%freight-in%')
                    ->orWhere('name_en', 'like', '%purchase freight%')
                    ->orWhere('name_en', 'like', '%purchase shipping%');
            })
            ->whereDoesntHave('children')
            ->orderBy('id')
            ->first();
    }

    /**
     * إنشاء حساب شحن مشتريات تلقائياً تحت مجموعة مصروفات تشغيلية عند غيابه (مثل تشغيل الـ seeder).
     *
     * @throws \RuntimeException إن تعذر العثور على أب مناسب في شجرة المصروفات
     */
    public static function ensureFreightInExpenseAccount(): self
    {
        $acc = static::resolveFreightInExpenseAccount();
        if ($acc) {
            return $acc;
        }

        $parent = static::resolveDefaultOperatingExpenseParent();
        if (! $parent) {
            throw new \RuntimeException(
                'حساب «شحن مشتريات» غير معرّف في شجرة الحسابات (detail_type=freight_in أو اسم يحتوي شحن مشتريات)، ولم يُعثر على مجموعة «مصروفات تشغيلية» لإنشائه تلقائياً. أضف الحساب يدوياً أو شغّل: php artisan db:seed --class=AccountingInventoryShippingAccountsSeeder'
            );
        }

        $level = (int) $parent->level + 1;
        $nextCode = static::nextNumericAccountCodeUnderParent($parent);

        return static::create([
            'name' => 'شحن مشتريات (توريد)',
            'name_en' => 'Purchase freight-in',
            'code' => (string) $nextCode,
            'parent_id' => $parent->id,
            'type' => 'expense',
            'level' => $level,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
            'detail_type' => 'freight_in',
        ]);
    }

    /**
     * أب شائع لمصروفات تشغيلية (إدراج حسابات شحن افتراضية تحته).
     */
    public static function resolveDefaultOperatingExpenseParent(): ?self
    {
        foreach ([50001, '50001', 5001, '5001'] as $code) {
            $a = static::where('code', (string) $code)->where('type', 'expense')->first();
            if ($a) {
                return $a;
            }
        }

        return static::where('type', 'expense')
            ->where(function ($q) {
                $q->where('name', 'like', '%تشغيل%')
                    ->orWhere('name', 'like', '%Operating%')
                    ->orWhere('name_en', 'like', '%operating%');
            })
            ->orderBy('level')
            ->orderBy('id')
            ->first()
            ?? static::where('type', 'expense')->where('level', 2)->orderBy('id')->first();
    }

    /**
     * حساب مصروف تشغيلي عام للقيد عندما لا تُحدَّد فئة بحساب شجرة — يتجنّب ربط كل شيء بـ«رواتب موظفين»
     * (أول فرع تحت مصروفات تشغيلية في الـ seeder الافتراضي).
     */
    public static function resolveOperatingExpenseFallbackLedgerAccount(): ?self
    {
        $parent = static::resolveDefaultOperatingExpenseParent();
        if (! $parent) {
            return null;
        }

        $existing = static::where('type', 'expense')
            ->where('parent_id', $parent->id)
            ->where('detail_type', 'general_operating_expense')
            ->whereDoesntHave('children')
            ->first();
        if ($existing) {
            return $existing;
        }

        $level = (int) $parent->level + 1;
        $nextCode = static::nextNumericAccountCodeUnderParent($parent);

        return static::create([
            'name' => 'مصروفات تشغيلية عامة',
            'name_en' => 'General operating expenses',
            'code' => (string) $nextCode,
            'parent_id' => $parent->id,
            'type' => 'expense',
            'level' => $level,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
            'detail_type' => 'general_operating_expense',
        ]);
    }

    /**
     * حساب الشجرة المدين في قيد المصروف حسب نوع المصروف (مصروف تشغيل / تسويق / ادارى).
     * يدعم شجرة الـ seeder التي لا تُنشئ حسابات بأسماء مطابقة لنوع المصروف ومستوى 4.
     */
    public static function resolveExpenseTypeLedgerAccount(string $expenseType): ?self
    {
        $trimmed = trim($expenseType);
        if ($trimmed === '') {
            return null;
        }

        $byName = static::where('type', 'expense')
            ->where('name', $trimmed)
            ->whereDoesntHave('children')
            ->orderByDesc('level')
            ->first();
        if ($byName) {
            return $byName;
        }

        if ($trimmed === 'مصروف تشغيل') {
            $general = static::resolveOperatingExpenseFallbackLedgerAccount();
            if ($general) {
                return $general;
            }

            $p = static::resolveDefaultOperatingExpenseParent();

            return $p ? static::firstLeafUnderAccount($p) : null;
        }

        if ($trimmed === 'مصروف ادارى') {
            $p = static::where('type', 'expense')
                ->where(function ($q) {
                    $q->where('name', 'مصروفات إدارية')
                        ->orWhere('name', 'مصروفات ادارية')
                        ->orWhere('name', 'like', '%مصروفات إدارية%')
                        ->orWhere('name', 'like', '%مصروفات ادارية%');
                })
                ->orderBy('level')
                ->first();
            if ($p) {
                return static::firstLeafUnderAccount($p);
            }

            return static::resolveExpenseTypeLedgerAccount('مصروف تشغيل');
        }

        if ($trimmed === 'مصروف تسويق') {
            $p = static::where('type', 'expense')
                ->where(function ($q) {
                    $q->where('name', 'like', '%تسويق%')
                        ->orWhere('name_en', 'like', '%market%');
                })
                ->where('level', '<=', 3)
                ->orderBy('level')
                ->orderBy('id')
                ->first();
            if ($p) {
                return static::firstLeafUnderAccount($p);
            }

            return static::resolveExpenseTypeLedgerAccount('مصروف تشغيل');
        }

        return null;
    }

    /**
     * حساب الأب لمصروفات «نقد صادر» — من الإعدادات أو بالاسم «النقد الصادر».
     */
    public static function resolveCashOutExpenseParent(): ?self
    {
        $settingId = Setting::where('key', 'cash_out_expense_parent_account_id')->value('value');
        if ($settingId) {
            $acc = static::query()->whereKey((int) $settingId)->where('type', 'expense')->first();
            if ($acc) {
                return $acc;
            }
        }

        $acc = static::query()
            ->where('type', 'expense')
            ->where('detail_type', 'cash_out_expenses')
            ->orderBy('level')
            ->orderBy('id')
            ->first();
        if ($acc) {
            return $acc;
        }

        foreach (['النقد الصادر', 'نقد صادر', 'النقد صادر'] as $name) {
            $acc = static::query()
                ->where('type', 'expense')
                ->where(function ($q) use ($name) {
                    $q->where('name', $name)
                        ->orWhere('name', 'like', '%'.$name.'%');
                })
                ->orderBy('level')
                ->orderBy('id')
                ->first();
            if ($acc) {
                return $acc;
            }
        }

        return null;
    }

    /**
     * إنشاء حساب «النقد الصادر» تحت جذر المصروفات إن لم يكن موجوداً.
     */
    public static function ensureCashOutExpenseParent(): self
    {
        $existing = static::resolveCashOutExpenseParent();
        if ($existing) {
            return $existing;
        }

        $expensesRoot = static::query()
            ->where('code', '5000')
            ->where('type', 'expense')
            ->first()
            ?? static::query()->where('type', 'expense')->whereNull('parent_id')->orderBy('id')->first();

        if (! $expensesRoot) {
            throw new \RuntimeException('لم يُعثر على حساب جذر المصروفات في شجرة الحسابات لإنشاء «النقد الصادر».');
        }

        $level = (int) $expensesRoot->level + 1;
        $nextCode = (string) static::nextNumericAccountCodeUnderParent($expensesRoot);
        while (static::where('code', $nextCode)->exists()) {
            $nextCode = (string) ((int) preg_replace('/\D/', '', $nextCode) + 1);
        }

        return static::create([
            'name' => 'النقد الصادر',
            'name_en' => 'Cash out expenses',
            'code' => $nextCode,
            'parent_id' => $expensesRoot->id,
            'type' => 'expense',
            'level' => $level,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
            'detail_type' => 'cash_out_expenses',
        ]);
    }

    public static function isUnderCashOutExpenseParent(self $account): bool
    {
        $parent = static::resolveCashOutExpenseParent();
        if (! $parent) {
            return false;
        }

        return static::isDescendantOf($account, (int) $parent->id) || (int) $account->id === (int) $parent->id;
    }

    public static function isDescendantOf(self $account, int $ancestorId): bool
    {
        $current = $account;
        $guard = 0;
        while ($current->parent_id && $guard < 32) {
            if ((int) $current->parent_id === $ancestorId) {
                return true;
            }
            $current = static::query()->whereKey((int) $current->parent_id)->first();
            if (! $current) {
                break;
            }
            $guard++;
        }

        return false;
    }

    /**
     * حساب طرفي تحت «النقد الصادر» باسم فئة المصروف (يُنشأ تلقائياً عند الحاجة).
     */
    public static function ensureExpenseKindLedgerAccount(ExpenseKind $kind, string $expenseType): self
    {
        if ($kind->tree_account_id) {
            $linked = static::query()
                ->whereKey((int) $kind->tree_account_id)
                ->where('type', 'expense')
                ->first();
            if ($linked) {
                return $linked;
            }
        }

        $parent = static::ensureCashOutExpenseParent();
        $label = trim((string) $kind->expense_kind);
        if ($label === '') {
            $label = trim($expenseType) !== '' ? trim($expenseType) : 'مصروف عام';
        }

        $existing = static::query()
            ->where('parent_id', $parent->id)
            ->where('type', 'expense')
            ->where('name', $label)
            ->whereDoesntHave('children')
            ->first();
        if ($existing) {
            if (! $kind->tree_account_id) {
                $kind->tree_account_id = $existing->id;
                $kind->saveQuietly();
            }

            return $existing;
        }

        $level = (int) $parent->level + 1;
        $nextCode = (string) static::nextNumericAccountCodeUnderParent($parent);
        while (static::where('code', $nextCode)->exists()) {
            $nextCode = (string) ((int) preg_replace('/\D/', '', $nextCode) + 1);
        }

        $created = static::create([
            'name' => $label,
            'code' => $nextCode,
            'parent_id' => $parent->id,
            'type' => 'expense',
            'level' => $level,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        if (! $kind->tree_account_id) {
            $kind->tree_account_id = $created->id;
            $kind->saveQuietly();
        }

        return $created;
    }

    /**
     * أوراق المصروف تحت حساب «النقد الصادر» (للقوائم في الواجهة).
     *
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function cashOutExpenseLeafAccounts()
    {
        $parent = static::ensureCashOutExpenseParent();
        $ids = static::query()
            ->where('type', 'expense')
            ->where('id', '!=', $parent->id)
            ->get(['id', 'parent_id'])
            ->filter(fn (self $acc) => static::isDescendantOf($acc, (int) $parent->id))
            ->pluck('id');

        return static::query()
            ->whereIn('id', $ids)
            ->whereDoesntHave('children')
            ->orderBy('code')
            ->get();
    }

    /**
     * حساب المدين في قيد المصروف: يفضّل الحساب المربوط بفئة المصروف (expense_kinds.tree_account_id)،
     * ثم حساب فرعي تحت «النقد الصادر» باسم الفئة، ثم الاحتياطي حسب نوع المصروف الرئيسي.
     */
    public static function resolveExpenseDebitForKind(?ExpenseKind $kind, string $expenseType): ?self
    {
        if ($kind && $kind->tree_account_id) {
            $acc = static::query()
                ->whereKey((int) $kind->tree_account_id)
                ->where('type', 'expense')
                ->first();
            if ($acc) {
                return $acc;
            }
        }

        if ($kind) {
            try {
                return static::ensureExpenseKindLedgerAccount($kind, $expenseType);
            } catch (\Throwable) {
                // fallback below
            }
        }

        return static::resolveExpenseTypeLedgerAccount($expenseType);
    }

    private static function firstLeafUnderAccount(self $account): self
    {
        $current = $account;
        while (true) {
            $child = $current->children()->orderBy('id')->first();
            if (! $child) {
                return $current;
            }
            $current = $child;
        }
    }

    private static function nextNumericAccountCodeUnderParent(self $parent): int
    {
        $row = static::where('parent_id', $parent->id)
            ->selectRaw('MAX(CAST(code AS UNSIGNED)) as mx')
            ->first();
        $max = $row && $row->mx !== null ? (int) $row->mx : 0;
        if ($max > 0) {
            return $max + 1;
        }

        $p = (int) preg_replace('/\D/', '', (string) $parent->code);
        if ($p <= 0) {
            $p = 50001;
        }

        return $p * 10 + 1;
    }

    /**
     * ذمم شركات الشحن / مندوبين (دائن).
     * detail_type المقترح: shipping_courier_payable
     */
    public static function resolveShippingCourierPayableAccount(): ?self
    {
        $acc = static::where('detail_type', 'shipping_courier_payable')->whereDoesntHave('children')->first();
        if ($acc) {
            return $acc;
        }

        $withDetail = static::where('detail_type', 'shipping_courier_payable')->orderBy('id')->first();
        if ($withDetail) {
            return static::firstLeafUnderAccount($withDetail);
        }

        return static::where('type', 'liability')
            ->where(function ($q) {
                $q->where('name', 'like', '%ذمم شركات شحن%')
                    ->orWhere('name', 'like', '%مستحق شحن%')
                    ->orWhere('name_en', 'like', '%shipping%payable%');
            })
            ->whereDoesntHave('children')
            ->orderBy('id')
            ->first();
    }

    /**
     * ذمم شركات الشحن — إنشاء تلقائي عند غياب الحساب (مثل AccountingInventoryShippingAccountsSeeder).
     *
     * @throws \RuntimeException إن تعذر العثور على مجموعة خصوم متداولة مناسبة
     */
    public static function ensureShippingCourierPayableAccount(): self
    {
        $acc = static::resolveShippingCourierPayableAccount();
        if ($acc) {
            return $acc;
        }

        $parent = static::where('code', '20001')->first()
            ?? static::where('type', 'liability')->where('level', 2)->orderBy('id')->first();

        if (! $parent) {
            throw new \RuntimeException(
                'حساب «ذمم شركات شحن» غير معرّف (detail_type=shipping_courier_payable)، ولم يُعثر على مجموعة خصوم متداولة لإنشائه تلقائياً. حدد حساب ذمم لشركة الشحن (tree_account_id) أو أضف الحساب يدوياً أو شغّل: php artisan db:seed --class=AccountingInventoryShippingAccountsSeeder'
            );
        }

        $level = (int) $parent->level + 1;
        $nextCode = static::nextNumericAccountCodeUnderParent($parent);

        return static::create([
            'name' => 'ذمم شركات شحن',
            'name_en' => 'Shipping companies payable',
            'code' => (string) $nextCode,
            'parent_id' => $parent->id,
            'type' => 'liability',
            'level' => $level,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
            'detail_type' => 'shipping_courier_payable',
        ]);
    }

    /**
     * إيراد شحن/توصيل يُحصّل من العميل (ليس مصروف الناقل).
     * detail_type المقترح: shipping_revenue
     */
    public static function resolveShippingRevenueAccount(): ?self
    {
        $acc = static::where('detail_type', 'shipping_revenue')->whereDoesntHave('children')->first();
        if ($acc) {
            return $acc;
        }

        return static::where('type', 'revenue')
            ->where(function ($q) {
                $q->where('name', 'like', '%إيراد شحن%')
                    ->orWhere('name', 'like', '%شحن محصل%')
                    ->orWhere('name_en', 'like', '%shipping%revenue%');
            })
            ->whereDoesntHave('children')
            ->orderBy('id')
            ->first();
    }

    /**
     * مصروف شحن صادر (دفع لشركة شحن/مندوب) — بند بيع/توزيع.
     * detail_type المقترح: freight_out
     */
    public static function resolveFreightOutExpenseAccount(): ?self
    {
        $acc = static::where('detail_type', 'freight_out')->whereDoesntHave('children')->first();
        if ($acc) {
            return $acc;
        }

        $withDetail = static::where('detail_type', 'freight_out')->orderBy('id')->first();
        if ($withDetail) {
            return static::firstLeafUnderAccount($withDetail);
        }

        return static::where('type', 'expense')
            ->where(function ($q) {
                $q->where('name', 'like', '%شحن صادر%')
                    ->orWhere('name', 'like', '%مصروف شحن بيع%')
                    ->orWhere('name_en', 'like', '%freight out%');
            })
            ->whereDoesntHave('children')
            ->orderBy('id')
            ->first();
    }

    /**
     * إنشاء حساب مصروف شحن صادر تلقائياً تحت مجموعة مصروفات تشغيلية عند غيابه.
     *
     * @throws \RuntimeException إن تعذر العثور على أب مناسب في شجرة المصروفات
     */
    public static function ensureFreightOutExpenseAccount(): self
    {
        $acc = static::resolveFreightOutExpenseAccount();
        if ($acc) {
            return $acc;
        }

        $parent = static::resolveDefaultOperatingExpenseParent();
        if (! $parent) {
            throw new \RuntimeException(
                'حساب «مصروف شحن صادر» غير معرّف في شجرة الحسابات (detail_type=freight_out)، ولم يُعثر على مجموعة «مصروفات تشغيلية» لإنشائه تلقائياً. أضف الحساب يدوياً أو شغّل: php artisan db:seed --class=AccountingInventoryShippingAccountsSeeder'
            );
        }

        $level = (int) $parent->level + 1;
        $nextCode = static::nextNumericAccountCodeUnderParent($parent);

        return static::create([
            'name' => 'مصروف شحن صادر (توصيل)',
            'name_en' => 'Outbound freight / delivery',
            'code' => (string) $nextCode,
            'parent_id' => $parent->id,
            'type' => 'expense',
            'level' => $level,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
            'detail_type' => 'freight_out',
        ]);
    }

    /**
     * Prefer a leaf COGS / cost-of-sales expense account.
     */
    public static function resolveCogsAccount(): ?self
    {
        $acc = static::where('detail_type', 'cogs')->whereDoesntHave('children')->first();
        if ($acc) {
            return $acc;
        }

        $acc = static::where('type', 'expense')
            ->where(function ($q) {
                $q->where('name', 'like', '%تكلفة%')
                    ->orWhere('name', 'like', '%تكاليف%')
                    ->orWhere('name', 'like', '%تكلفة المبيعات%')
                    ->orWhere('name', 'like', '%تكلفة البضاعة%')
                    ->orWhere('name_en', 'like', '%cost%')
                    ->orWhere('name_en', 'like', '%cogs%');
            })
            ->whereDoesntHave('children')
            ->orderByRaw('LENGTH(code) DESC')
            ->orderBy('code')
            ->first();

        return $acc;
    }

    /**
     * Prefer a leaf asset account for inventory (stock) used in COGS journal pairs.
     */
    public static function resolveInventoryAccount(): ?self
    {
        $acc = static::where('detail_type', 'inventory')->whereDoesntHave('children')->first();
        if ($acc) {
            return $acc;
        }

        return static::where('type', 'asset')
            ->where(function ($q) {
                $q->where('name', 'like', '%مخزون%')
                    ->orWhere('name_en', 'like', '%inventory%')
                    ->orWhere('name_en', 'like', '%stock%');
            })
            ->whereDoesntHave('children')
            ->orderByRaw('LENGTH(code) DESC')
            ->orderBy('code')
            ->first();
    }

    /**
     * حساب مخزون الجرد المرتبط بصف مخزن (جدول stocks عبر asset_id).
     */
    public static function resolveInventoryAccountForStock(?Stock $stock): ?self
    {
        if ($stock && $stock->asset_id) {
            $acc = static::query()->whereKey((int) $stock->asset_id)->first();
            if ($acc && $acc->type === 'asset') {
                return $acc;
            }
        }

        if ($stock) {
            $wt = trim((string) ($stock->warehouse_type ?? ''));
            $name = trim((string) ($stock->name ?? ''));
            if ($wt === 'wip' || $name === 'مخزن منتج تحت التشغيل') {
                return static::resolveInventoryWipAccount();
            }
            if ($wt === 'raw_materials' || $name === 'مخزن مواد خام') {
                return static::resolveInventoryRawAccount();
            }
            if ($wt === 'finished_goods' || $name === 'مخزن منتج تام') {
                return static::resolveInventoryFinishedAccount();
            }
        }

        return static::resolveInventoryAccount();
    }

    /**
     * حساب مخزون الصنف من categories (يفضّل stock_id ثم مطابقة اسم المخزن مع stocks.name).
     */
    public static function resolveInventoryAccountForCategoryRow(?object $categoryRow): ?self
    {
        if (! $categoryRow) {
            return static::resolveInventoryAccount();
        }

        $stock = null;
        if (! empty($categoryRow->stock_id)) {
            $stock = Stock::query()->find((int) $categoryRow->stock_id);
        }
        if (! $stock && ! empty($categoryRow->warehouse)) {
            $stock = Stock::query()->where('name', trim((string) $categoryRow->warehouse))->first();
        }

        $resolved = static::resolveInventoryAccountForStock($stock);
        if ($resolved) {
            return $resolved;
        }

        $w = trim((string) ($categoryRow->warehouse ?? ''));
        if ($w === 'مخزن منتج تحت التشغيل') {
            return static::resolveInventoryWipAccount();
        }
        if ($w === 'مخزن مواد خام') {
            return static::resolveInventoryRawAccount();
        }
        if ($w === 'مخزن منتج تام') {
            return static::resolveInventoryFinishedAccount();
        }

        return static::resolveInventoryAccount();
    }

    public static function resolveInventoryAccountForCategoryId(int $categoryId): ?self
    {
        $row = DB::table('categories')->where('id', $categoryId)->first();

        return static::resolveInventoryAccountForCategoryRow($row);
    }

    /**
     * حساب موازنة لرصيد مخزون افتتاحي (دائن) — حقوق ملكية أو جاري.
     */
    public static function resolveOpeningInventoryOffsetAccount(): ?self
    {
        $acc = static::where('detail_type', 'opening_inventory_offset')->whereDoesntHave('children')->first();
        if ($acc) {
            return $acc;
        }

        return static::where('type', 'equity')
            ->where(function ($q) {
                $q->where('name', 'like', '%رأس المال%')
                    ->orWhere('name', 'like', '%جاري%')
                    ->orWhere('name_en', 'like', '%equity%');
            })
            ->whereDoesntHave('children')
            ->orderBy('id')
            ->first()
            ?? static::where('type', 'equity')->whereDoesntHave('children')->orderBy('id')->first();
    }

    public static function resolveInventoryRawAccount(): ?self
    {
        return static::where('detail_type', 'inventory_raw')->whereDoesntHave('children')->first()
            ?? static::resolveInventoryAccount();
    }

    public static function resolveInventoryWipAccount(): ?self
    {
        return static::where('detail_type', 'inventory_wip')->whereDoesntHave('children')->first()
            ?? static::resolveInventoryAccount();
    }

    public static function resolveInventoryFinishedAccount(): ?self
    {
        return static::where('detail_type', 'inventory_finished')->whereDoesntHave('children')->first()
            ?? static::resolveInventoryAccount();
    }

    public static function resolveInventoryAdjustmentLossAccount(): ?self
    {
        return static::where('detail_type', 'inventory_adjustment_loss')->whereDoesntHave('children')->first();
    }

    public static function resolveInventoryAdjustmentGainAccount(): ?self
    {
        return static::where('detail_type', 'inventory_adjustment_gain')->whereDoesntHave('children')->first();
    }
}