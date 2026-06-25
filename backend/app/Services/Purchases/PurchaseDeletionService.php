<?php

namespace App\Services\Purchases;

use App\Models\Approvals;
use App\Models\Bank;
use App\Models\Purchase;
use App\Models\PurchasesTracking;
use App\Models\Safe;
use App\Models\ServiceAccount;
use App\Models\StockTransaction;
use App\Models\Supplier;
use App\Models\TreeAccount;
use App\Services\Accounting\InventoryGlPostingService;
use App\Services\CategoryInventoryCostService;
use Illuminate\Support\Facades\DB;

class PurchaseDeletionService
{
    public function __construct(
        private PurchaseInvoiceTypeResolver $typeResolver,
        private PurchaseInvoiceAccountingService $purchaseAccounting,
    ) {}

    /**
     * @return array{purchase: Purchase, main: Purchase}
     *
     * @throws \InvalidArgumentException
     */
    public function resolveForDeletion(int $mainPurchaseId): array
    {
        $main = Purchase::query()->find($mainPurchaseId);
        if (! $main) {
            throw new \InvalidArgumentException('فاتورة المشتريات غير موجودة.');
        }

        if ((string) $main->status === '1') {
            throw new \InvalidArgumentException('فاتورة المشتريات محذوفة مسبقاً.');
        }

        $purchase = Purchase::query()
            ->where('invoice_number', $main->invoice_number)
            ->with(['bank:id,name', 'safe:id,name', 'serviceAccount:id,name', 'supplier:id,supplier_name'])
            ->latest('id')
            ->first();

        if (! $purchase) {
            throw new \InvalidArgumentException('تعذر العثور على بيانات الفاتورة.');
        }

        return ['purchase' => $purchase, 'main' => $main];
    }

    public function requestApproval(int $mainPurchaseId, int $userId): Approvals
    {
        ['purchase' => $purchase] = $this->resolveForDeletion($mainPurchaseId);

        $isExist = Approvals::query()
            ->where('table_name', 'purchases')
            ->where('type', 'delete')
            ->where('status', 'pending')
            ->where('column_values->id', $purchase->id)
            ->first();

        if ($isExist) {
            throw new \InvalidArgumentException('طلب حذف هذه الفاتورة قيد انتظار موافقة الأدمن.');
        }

        return Approvals::create([
            'type' => 'delete',
            'table_name' => 'purchases',
            'column_values' => $purchase,
            'details' => $purchase,
            'user_id' => $userId,
        ]);
    }

    public function delete(int $mainPurchaseId, int $userId): void
    {
        ['purchase' => $purchase, 'main' => $main] = $this->resolveForDeletion($mainPurchaseId);

        DB::transaction(function () use ($purchase, $main, $userId) {
            $lockedMain = Purchase::query()->whereKey($main->id)->lockForUpdate()->firstOrFail();
            if ((string) $lockedMain->status === '1') {
                throw new \InvalidArgumentException('فاتورة المشتريات محذوفة مسبقاً.');
            }

            $kind = $this->typeResolver->kind($purchase->invoice_type);
            $oldCategories = DB::table('invoice_categories')->where('purchase_id', $purchase->id)->get();
            $this->purchaseAccounting->reverseProductLines($purchase, $oldCategories, $kind);
            $this->deleteStockDocuments($purchase);
            $this->markDeleted($main, $purchase, $userId);
            $this->reverseSupplierBalance($purchase, $userId);
            $this->reverseAccounting($purchase, $oldCategories, $kind, $userId);
        });
    }

    /**
     * @param  list<int>  $mainPurchaseIds
     * @return array{
     *     deleted: list<int>,
     *     pending: list<array{id:int, approval_id:int}>,
     *     failed: list<array{id:int, message:string}>
     * }
     */
    public function deleteMany(array $mainPurchaseIds, int $userId, bool $isAdmin): array
    {
        $results = [
            'deleted' => [],
            'pending' => [],
            'failed' => [],
        ];

        foreach (array_values(array_unique(array_map('intval', $mainPurchaseIds))) as $id) {
            try {
                if ($isAdmin) {
                    $this->delete($id, $userId);
                    $results['deleted'][] = $id;
                } else {
                    $approval = $this->requestApproval($id, $userId);
                    $results['pending'][] = [
                        'id' => $id,
                        'approval_id' => (int) $approval->id,
                    ];
                }
            } catch (\Throwable $e) {
                $results['failed'][] = [
                    'id' => $id,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    private function deleteStockDocuments(Purchase $purchase): void
    {
        $mainId = $purchase->ref ? (int) $purchase->ref : (int) $purchase->id;
        $chainPurchaseIds = Purchase::query()
            ->where(function ($q) use ($mainId) {
                $q->where('id', $mainId)->orWhere('ref', $mainId);
            })
            ->pluck('id')
            ->all();

        StockTransaction::query()
            ->where('reference_type', 'purchase')
            ->whereIn('reference_id', $chainPurchaseIds)
            ->delete();
    }

    private function markDeleted(Purchase $main, Purchase $purchase, int $userId): void
    {
        $main->status = '1';
        $main->save();

        PurchasesTracking::create([
            'invoice_id' => $main->id,
            'invoice_number' => $purchase->id,
            'action' => 'حذف فاتورة',
            'user_id' => $userId,
        ]);
    }

    private function reverseSupplierBalance(Purchase $purchase, int $userId): void
    {
        $kind = $this->typeResolver->kind($purchase->invoice_type);
        if (! $this->typeResolver->affectsSupplierBalance($kind)) {
            return;
        }

        $supplier = Supplier::find($purchase->supplier_id);
        if (! $supplier) {
            return;
        }

        $linesSum = (float) DB::table('invoice_categories')->where('purchase_id', $purchase->id)->sum('total');
        $delShip = (float) ($purchase->shipping_total ?? $purchase->transport_cost);
        $delProduct = (float) ($purchase->product_total ?? abs($linesSum));
        $grandTotal = $delProduct + $delShip;
        $delta = $this->typeResolver->supplierBalanceDelta($kind, (float) $purchase->due_amount, $grandTotal);

        $supplier->last_balance = $supplier->balance;
        $supplier->balance -= $delta;
        $supplier->save();

        DB::table('supplier_balance')->insert([
            'invoice_id' => $purchase->id,
            'balance_before' => $supplier->last_balance,
            'balance_after' => $supplier->balance,
            'user_id' => $userId,
        ]);
    }

    private function reverseAccounting(Purchase $purchase, $oldCategories, $kind, int $userId): void
    {
        $linesSumDelete = abs((float) $oldCategories->sum('total'));
        $supplier = Supplier::find($purchase->supplier_id);
        $glService = app(InventoryGlPostingService::class);

        $delShip = (float) ($purchase->shipping_total ?? $purchase->transport_cost);
        $delProduct = (float) ($purchase->product_total ?? $linesSumDelete);

        $delInvMap = CategoryInventoryCostService::aggregatePurchaseLineTotalsByInventoryTreeAccount($oldCategories);
        if ($delProduct > 0.00001 && count($delInvMap) === 0 && $kind !== \App\Enums\PurchaseInvoiceKind::Amanat) {
            $fb = TreeAccount::resolveInventoryAccount();
            if ($fb) {
                $delInvMap[$fb->id] = $delProduct;
            }
        }

        if ($supplier) {
            $this->purchaseAccounting->reverseGlForPurchase(
                $purchase,
                $supplier,
                $delInvMap,
                $delShip,
                $delProduct,
                $kind,
                $userId
            );
        }

        if ((float) $purchase->paid_amount > 0.00001 && $supplier) {
            if ($kind === \App\Enums\PurchaseInvoiceKind::PurchaseReturn) {
                $glService->postPurchasePaymentGl($purchase, $supplier, (float) $purchase->paid_amount, $userId);
            } elseif ($this->typeResolver->affectsSupplierBalance($kind)) {
                $glService->reversePurchasePaymentGl($purchase, $supplier, (float) $purchase->paid_amount, $userId);
            }
        }

        if ((float) $purchase->paid_amount > 0) {
            if ($kind === \App\Enums\PurchaseInvoiceKind::PurchaseReturn) {
                $this->deductPurchasePaymentSource($purchase, (float) $purchase->paid_amount, $userId);
            } else {
                $this->refundPurchasePayment($purchase, (float) $purchase->paid_amount, $userId);
            }
        }
    }

    private function deductPurchasePaymentSource(Purchase $purchase, float $amount, int $userId): void
    {
        $pt = $purchase->payment_type ?? 'bank';
        if ($pt === 'safe' && $purchase->safe_id) {
            Safe::where('id', $purchase->safe_id)->decrement('balance', $amount);
        } elseif ($pt === 'service_account' && $purchase->service_account_id) {
            ServiceAccount::where('id', $purchase->service_account_id)->decrement('balance', $amount);
        } elseif ($purchase->bank_id) {
            $bank = Bank::find($purchase->bank_id);
            if ($bank) {
                $balanceBefore = (float) $bank->balance;
                $bank->decrement('balance', $amount);
                DB::table('bank_details')->insert([
                    'bank_id' => $purchase->bank_id,
                    'details' => ' عكس استرداد مرتجع مشتريات رقم '.$purchase->invoice_number,
                    'ref' => $purchase->invoice_number,
                    'type' => 'مرتجع مشتريات',
                    'amount' => $amount * -1,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $bank->fresh()->balance,
                    'date' => date('Y-m-d'),
                    'created_at' => now(),
                    'user_id' => $userId,
                ]);
            }
        }
    }

    private function refundPurchasePayment(Purchase $purchase, float $amount, int $userId): void
    {
        $pt = $purchase->payment_type ?? 'bank';
        if ($pt === 'safe' && $purchase->safe_id) {
            Safe::where('id', $purchase->safe_id)->increment('balance', $amount);
        } elseif ($pt === 'service_account' && $purchase->service_account_id) {
            ServiceAccount::where('id', $purchase->service_account_id)->increment('balance', $amount);
        } elseif ($purchase->bank_id) {
            $bank = Bank::find($purchase->bank_id);
            if ($bank) {
                $balanceBefore = (float) $bank->balance;
                $bank->increment('balance', $amount);
                DB::table('bank_details')->insert([
                    'bank_id' => $purchase->bank_id,
                    'details' => ' مرتجع فاتورة مشتريات رقم '.$purchase->invoice_number,
                    'ref' => $purchase->invoice_number,
                    'type' => 'مرتجع',
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $bank->fresh()->balance,
                    'date' => date('Y-m-d'),
                    'created_at' => now(),
                    'user_id' => $userId,
                ]);
            }
        }
    }
}
