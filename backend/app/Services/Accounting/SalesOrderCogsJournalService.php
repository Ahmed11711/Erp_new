<?php

namespace App\Services\Accounting;

use App\Models\AccountEntry;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderShipmentLine;
use App\Models\TreeAccount;
use App\Services\CategoryInventoryCostService;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * ترحيل قيد خروج المخزن التام (COGS) للطلبات المشحونة دون المساس بكمية المخزن.
 *
 * المصدر بالتكلفة — أول مصدر فيه مبلغ أكبر من صفر:
 * 1) أسطر الشحن المحفوظة (order_shipment_lines.line_cogs)
 * 2) حركة المخزن التاريخية (categories_balance نوع «شحن طلب»)
 * 3) الكمية المشحونة × متوسط التكلفة الحالي
 */
class SalesOrderCogsJournalService
{
    public const ORDER_TYPES = ['جديد', 'طلب استبدال'];

    private const EXCLUDED_STATUSES = ['ملغي', 'أرشيف'];

    public function __construct(
        private InventoryGlPostingService $inventoryGl,
    ) {
    }

    public function isEligible(Order $order): bool
    {
        if (! in_array((string) $order->order_type, self::ORDER_TYPES, true)) {
            return false;
        }
        if (in_array((string) $order->order_status, self::EXCLUDED_STATUSES, true)) {
            return false;
        }

        $shippingDate = $order->relationLoaded('order_details')
            ? $order->order_details?->shipping_date
            : $order->order_details()->value('shipping_date');

        return $shippingDate !== null
            || in_array((string) $order->order_status, SalesOrderAccountingService::SHIPPED_PLUS_STATUSES, true);
    }

    public function hasCogsRecognition(Order $order): bool
    {
        return AccountEntry::query()
            ->where('order_id', $order->id)
            ->where(function ($q) use ($order) {
                $q->where('entry_batch_code', 'like', 'COGS-'.$order->id.'-%')
                    ->orWhere('description', 'like', 'تكلفة البضاعة المباعة للطلب رقم '.$order->id.'%');
            })
            ->exists();
    }

    public function hasCogsReversal(Order $order): bool
    {
        return AccountEntry::query()
            ->where('order_id', $order->id)
            ->where('entry_batch_code', 'like', 'RETURN-COGS-'.$order->id.'-%')
            ->exists();
    }

    /**
     * @param  list<int>  $orderIds
     * @return array<int, true>
     */
    public function postedCogsOrderIdSet(array $orderIds): array
    {
        $ids = array_values(array_unique(array_filter($orderIds)));
        if ($ids === []) {
            return [];
        }

        $posted = [];
        foreach (array_chunk($ids, 1500) as $chunk) {
            $rows = AccountEntry::query()
                ->whereIn('order_id', $chunk)
                ->where(function ($q) {
                    $q->whereRaw("entry_batch_code LIKE CONCAT('COGS-', order_id, '-%')")
                        ->orWhereRaw("description LIKE CONCAT('تكلفة البضاعة المباعة للطلب رقم ', order_id, '%')");
                })
                ->distinct()
                ->pluck('order_id');
            foreach ($rows as $id) {
                $posted[(int) $id] = true;
            }
        }

        return $posted;
    }

    /**
     * @return array{total: float, by_inventory_account: array<int, float>}
     */
    public function composeCogsAmounts(Order $order): array
    {
        $byCategory = $this->amountsByCategory($order);
        $byInv = [];
        foreach ($byCategory as $categoryId => $amount) {
            $amt = round((float) $amount, 2);
            if ($amt <= 0.00001) {
                continue;
            }
            $inv = TreeAccount::resolveInventoryAccountForCategoryId((int) $categoryId)
                ?? TreeAccount::resolveInventoryFinishedAccount()
                ?? TreeAccount::resolveInventoryAccount();
            if (! $inv) {
                continue;
            }
            $byInv[(int) $inv->id] = ($byInv[(int) $inv->id] ?? 0) + $amt;
        }

        return [
            'total' => round(array_sum($byInv), 2),
            'by_inventory_account' => $byInv,
        ];
    }

    /**
     * @return 'posted'|'skipped_exists'|'skipped_ineligible'|'skipped_zero'|'failed'
     */
    public function postIfMissing(Order $order, ?DateTimeInterface $date = null): string
    {
        if ($this->hasCogsRecognition($order)) {
            return 'skipped_exists';
        }
        if (! $this->isEligible($order)) {
            return 'skipped_ineligible';
        }
        if (! TreeAccount::resolveCogsAccount()) {
            Log::warning('SalesOrderCogsJournal: COGS account missing', [
                'order_id' => $order->id,
            ]);

            return 'failed';
        }

        $composed = $this->composeCogsAmounts($order);
        if ($composed['total'] <= 0.00001) {
            return 'skipped_zero';
        }

        try {
            $this->inventoryGl->postCogsShipment(
                $composed['total'],
                $composed['by_inventory_account'],
                'تكلفة البضاعة المباعة للطلب رقم '.$order->id,
                (int) $order->id,
                $date ?? $this->resolveJournalDate($order)
            );
        } catch (\Throwable $e) {
            Log::warning('SalesOrderCogsJournal: postCogsShipment failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return 'failed';
        }

        return $this->hasCogsRecognition($order) ? 'posted' : 'failed';
    }

    /**
     * مسودة قيد خروج المخزن التام للعرض — لا ترحّل.
     *
     * @return array{
     *     applicable: bool,
     *     posted: bool,
     *     can_post: bool,
     *     reason: string|null,
     *     total: float,
     *     lines: list<array{account_id: int, account_code: string|null, account_name: string|null, debit: float, credit: float, description: string}>
     * }
     */
    public function preview(Order $order): array
    {
        $order->loadMissing(['order_products', 'order_details']);
        $base = [
            'applicable' => false,
            'posted' => $this->hasCogsRecognition($order),
            'can_post' => false,
            'reason' => null,
            'date' => $this->resolveJournalDate($order)->toDateString(),
            'description' => 'تكلفة البضاعة المباعة للطلب رقم '.$order->id,
            'total' => 0.0,
            'lines' => [],
        ];

        if (! in_array((string) $order->order_type, self::ORDER_TYPES, true)) {
            $base['reason'] = 'نوع الطلب لا يخرج من المخزن التام (صيانة / غير مخزني).';

            return $base;
        }

        $base['applicable'] = $this->isEligible($order);
        if (! $base['applicable']) {
            $base['reason'] = 'الطلب لم يُشحن بعد — قيد خروج المخزن يُرحَّل بتاريخ الشحن.';

            return $base;
        }

        if ($base['posted']) {
            $base['reason'] = 'قيد خروج المخزن التام موجود مسبقاً.';

            return $base;
        }

        if (! TreeAccount::resolveCogsAccount()) {
            $base['reason'] = 'حساب تكلفة المبيعات غير مربوط في الشجرة.';

            return $base;
        }

        $composed = $this->composeCogsAmounts($order);
        $base['total'] = $composed['total'];
        if ($composed['total'] <= 0.00001) {
            $base['applicable'] = false;
            $base['reason'] = 'لا توجد تكلفة مخزن للترحيل: الطلب بدون تكلفة أصناف (مثل مصاريف أو إيراد الشحن). إيراد الشحن يترحل مع قيد إثبات المبيعات، ومصروف شركة الشحن قيد منفصل عند تسجيل تكلفة المندوب.';

            return $base;
        }

        $cogsAcc = TreeAccount::resolveCogsAccount();
        $description = 'تكلفة البضاعة المباعة للطلب رقم '.$order->id;
        $raw = [[
            'account_id' => (int) $cogsAcc->id,
            'debit' => round($composed['total'], 2),
            'credit' => 0.0,
            'description' => $description,
        ]];
        foreach ($composed['by_inventory_account'] as $accountId => $amount) {
            if ($amount <= 0.00001) {
                continue;
            }
            $raw[] = [
                'account_id' => (int) $accountId,
                'debit' => 0.0,
                'credit' => round((float) $amount, 2),
                'description' => $description,
            ];
        }

        $base['can_post'] = true;
        $base['date'] = $this->resolveJournalDate($order)->toDateString();
        $base['description'] = $description;
        $base['lines'] = $this->decorateLines($raw);

        return $base;
    }

    /**
     * @param  list<array{account_id: int, debit?: float|int, credit?: float|int, description?: string}>  $lines
     * @return 'posted'|'skipped_exists'|'skipped_ineligible'|'failed'
     */
    public function postIfMissingWithLines(
        Order $order,
        array $lines,
        ?string $description = null,
        ?DateTimeInterface $date = null
    ): string {
        if ($this->hasCogsRecognition($order)) {
            return 'skipped_exists';
        }
        if (! $this->isEligible($order)) {
            return 'skipped_ineligible';
        }

        $normalized = [];
        $debit = 0.0;
        $credit = 0.0;
        foreach ($lines as $line) {
            $accountId = (int) ($line['account_id'] ?? 0);
            $dr = round((float) ($line['debit'] ?? 0), 2);
            $cr = round((float) ($line['credit'] ?? 0), 2);
            if ($accountId < 1 || ($dr <= 0 && $cr <= 0)) {
                continue;
            }
            $normalized[] = [
                'account_id' => $accountId,
                'debit' => $dr,
                'credit' => $cr,
                'description' => trim((string) ($line['description'] ?? '')),
            ];
            $debit += $dr;
            $credit += $cr;
        }
        if ($normalized === [] || abs($debit - $credit) > 0.009) {
            return 'failed';
        }

        $header = trim((string) ($description ?: 'تكلفة البضاعة المباعة للطلب رقم '.$order->id));
        $batch = 'COGS-'.$order->id.'-'.now()->format('YmdHis');
        app(LedgerJournalService::class)->postBalancedJournal(
            $normalized,
            $header,
            (int) $order->id,
            $batch,
            null,
            $date ?? $this->resolveJournalDate($order)
        );

        return $this->hasCogsRecognition($order) ? 'posted' : 'failed';
    }

    /**
     * @param  list<array{account_id: int, debit: float, credit: float, description: string}>  $lines
     * @return list<array{account_id: int, account_code: string|null, account_name: string|null, debit: float, credit: float, description: string}>
     */
    private function decorateLines(array $lines): array
    {
        $ids = array_values(array_unique(array_map(fn ($line) => (int) $line['account_id'], $lines)));
        $accounts = TreeAccount::query()
            ->whereIn('id', $ids)
            ->get(['id', 'code', 'name'])
            ->keyBy('id');

        $out = [];
        foreach ($lines as $line) {
            $account = $accounts->get((int) $line['account_id']);
            $out[] = [
                'account_id' => (int) $line['account_id'],
                'account_code' => $account?->code !== null ? (string) $account->code : null,
                'account_name' => $account?->name !== null ? (string) $account->name : null,
                'debit' => round((float) $line['debit'], 2),
                'credit' => round((float) $line['credit'], 2),
                'description' => (string) $line['description'],
            ];
        }

        return $out;
    }

    /**
     * @return 'posted'|'skipped_exists'|'skipped_ineligible'|'skipped_zero'|'failed'
     */
    public function postReversalIfMissing(Order $order, ?DateTimeInterface $date = null): string
    {
        if (! in_array((string) $order->order_status, ['رفض استلام', 'مرتجع'], true)) {
            return 'skipped_ineligible';
        }
        if ($this->hasCogsReversal($order)) {
            return 'skipped_exists';
        }

        $composed = $this->composeCogsAmounts($order);
        if ($composed['total'] <= 0.00001) {
            return 'skipped_zero';
        }

        try {
            $this->inventoryGl->postSalesReturnInventoryRestoreByWarehouse(
                $composed['by_inventory_account'],
                'عكس تكلفة البضاعة المباعة — طلب رقم '.$order->id,
                null,
                (int) $order->id,
                $date
            );
        } catch (\Throwable $e) {
            Log::warning('SalesOrderCogsJournal: COGS reversal failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return 'failed';
        }

        return $this->hasCogsReversal($order) ? 'posted' : 'failed';
    }

    /**
     * @return array<int, float> category_id => cogs
     */
    private function amountsByCategory(Order $order): array
    {
        $fromShipments = $this->amountsFromShipmentLines((int) $order->id);
        if ($this->sumPositive($fromShipments) > 0.00001) {
            return $fromShipments;
        }

        $fromBalance = $this->amountsFromCategoryBalance((int) $order->id);
        if ($this->sumPositive($fromBalance) > 0.00001) {
            return $fromBalance;
        }

        return $this->amountsFromOrderProducts($order);
    }

    /**
     * @return array<int, float>
     */
    private function amountsFromShipmentLines(int $orderId): array
    {
        if (! Schema::hasTable('order_shipment_lines') || ! Schema::hasTable('order_shipments')) {
            return [];
        }

        $rows = OrderShipmentLine::query()
            ->select('order_shipment_lines.category_id', 'order_shipment_lines.line_cogs', 'order_shipment_lines.quantity', 'order_shipment_lines.unit_cost')
            ->join('order_shipments', 'order_shipments.id', '=', 'order_shipment_lines.order_shipment_id')
            ->where('order_shipments.order_id', $orderId)
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $categoryId = (int) ($row->category_id ?? 0);
            if ($categoryId <= 0) {
                continue;
            }
            $line = (float) ($row->line_cogs ?? 0);
            if ($line <= 0.00001) {
                $line = (float) ($row->quantity ?? 0) * (float) ($row->unit_cost ?? 0);
            }
            if ($line <= 0.00001) {
                continue;
            }
            $map[$categoryId] = ($map[$categoryId] ?? 0) + $line;
        }

        return $map;
    }

    /**
     * @return array<int, float>
     */
    private function amountsFromCategoryBalance(int $orderId): array
    {
        if (! Schema::hasTable('categories_balance')) {
            return [];
        }

        $query = DB::table('categories_balance')
            ->where('type', 'شحن طلب')
            ->where(function ($q) use ($orderId) {
                $q->where('invoice_number', (string) $orderId)
                    ->orWhere('invoice_number', $orderId);
            });

        if (Schema::hasColumn('categories_balance', 'cost_total')) {
            $query->selectRaw('category_id, SUM(COALESCE(cost_total, unit_cost * quantity, 0)) as cogs');
        } elseif (Schema::hasColumn('categories_balance', 'unit_cost')) {
            $query->selectRaw('category_id, SUM(COALESCE(unit_cost, 0) * COALESCE(quantity, 0)) as cogs');
        } else {
            return [];
        }

        $rows = $query->groupBy('category_id')->get();
        $map = [];
        foreach ($rows as $row) {
            $categoryId = (int) ($row->category_id ?? 0);
            $amt = (float) ($row->cogs ?? 0);
            if ($categoryId <= 0 || $amt <= 0.00001) {
                continue;
            }
            $map[$categoryId] = ($map[$categoryId] ?? 0) + $amt;
        }

        return $map;
    }

    /**
     * @return array<int, float>
     */
    private function amountsFromOrderProducts(Order $order): array
    {
        $order->loadMissing('order_products');
        $lines = $order->order_products;
        $anyShippedTracked = false;
        foreach ($lines as $line) {
            if ((float) ($line->shipped_quantity ?? 0) > 0.00001) {
                $anyShippedTracked = true;
                break;
            }
        }

        $map = [];
        foreach ($lines as $line) {
            /** @var OrderProduct $line */
            $categoryId = (int) $line->category_id;
            if ($categoryId <= 0) {
                continue;
            }
            $qty = (float) ($line->shipped_quantity ?? 0);
            if ($qty <= 0.00001) {
                if ($anyShippedTracked) {
                    continue;
                }
                $qty = max(0, (float) ($line->quantity ?? 0) - (float) ($line->cancelled_quantity ?? 0));
            }
            if ($qty <= 0.00001) {
                continue;
            }
            $unit = CategoryInventoryCostService::resolveReferenceUnitCost($categoryId);
            $lineCogs = $qty * $unit;
            if ($lineCogs <= 0.00001) {
                continue;
            }
            $map[$categoryId] = ($map[$categoryId] ?? 0) + $lineCogs;
        }

        return $map;
    }

    /**
     * @param  array<int, float>  $map
     */
    private function sumPositive(array $map): float
    {
        $sum = 0.0;
        foreach ($map as $amt) {
            $sum += max(0, (float) $amt);
        }

        return $sum;
    }

    private function resolveJournalDate(Order $order): Carbon
    {
        $order->loadMissing('order_details');
        foreach ([
            $order->order_details?->shipping_date,
            $order->order_date,
        ] as $raw) {
            if ($raw === null || $raw === '') {
                continue;
            }
            try {
                return Carbon::parse((string) $raw)->startOfDay();
            } catch (\Throwable $e) {
                continue;
            }
        }

        return now()->startOfDay();
    }
}
