<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
            $this->setAttribute('code', $resolved['code']);
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
}