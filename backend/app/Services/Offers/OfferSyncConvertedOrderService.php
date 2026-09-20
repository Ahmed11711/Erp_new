<?php

namespace App\Services\Offers;

use App\Models\Offers;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\customerCompany;
use App\Services\Accounting\OfferDebtAccountingService;
use App\Support\VatCalculator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OfferSyncConvertedOrderService
{
    /** حالات يمنع فيها تعديل أصناف/إجماليات الطلب المحوّل */
    public const BLOCKED_STATUSES = [
        'تم شحن',
        'تم التسليم',
        'شحن جزئي',
        'تسليم جزئي',
        'تم التحصيل',
        'ملغي',
        'أرشيف',
        'تم الاستلام',
    ];

    public function __construct(
        private OfferProductMatchService $productMatcher,
        private OfferDebtAccountingService $offerDebtAccounting,
    ) {}

    /**
     * مزامنة بنود وإجماليات الطلب المحوّل من العرض بعد التعديل.
     *
     * @param  array{create_missing?: bool}  $options
     * @return array{
     *   updated: bool,
     *   order_id?: int,
     *   debt_adjusted?: bool,
     *   message: string,
     *   unmatched_products?: list<string>,
     *   needs_product_link?: bool
     * }
     */
    public function sync(Offers $offer, array $options = []): array
    {
        $offer->loadMissing(['category', 'customerCompany']);

        if (! empty($options['create_missing'])) {
            $this->productMatcher->createMissingCategories($offer);
            $offer->unsetRelation('category');
            $offer->load(['category', 'customerCompany']);
        }

        $orderId = (int) ($offer->converted_order_id ?? 0);
        if ($orderId <= 0) {
            throw new \InvalidArgumentException('العرض غير محوّل إلى طلب.');
        }

        $order = Order::query()
            ->with('order_products')
            ->whereKey($orderId)
            ->first();

        if (! $order) {
            throw new \InvalidArgumentException('الطلب المحوّل غير موجود.');
        }

        if ((int) ($order->offer_id ?? 0) !== (int) $offer->id) {
            throw new \InvalidArgumentException('الطلب المرتبط لا يطابق هذا العرض.');
        }

        $status = trim((string) $order->order_status);
        if (in_array($status, self::BLOCKED_STATUSES, true)) {
            throw new \InvalidArgumentException(
                'لا يمكن تعديل الطلب رقم ' . $order->id . ' لأنه في حالة «' . $status . '».'
            );
        }

        $shippedQty = (float) $order->order_products->sum(
            static fn ($p) => (float) ($p->shipped_quantity ?? 0)
        );
        if ($shippedQty > 0.0001) {
            throw new \InvalidArgumentException(
                'لا يمكن تعديل أصناف الطلب رقم ' . $order->id . ' لأن جزءاً منه تم شحنه.'
            );
        }

        if ($order->orderShipments()->exists()) {
            throw new \InvalidArgumentException(
                'لا يمكن تعديل أصناف الطلب رقم ' . $order->id . ' لوجود دفعات شحن مسجّلة عليه.'
            );
        }

        [$details, $unmatched] = $this->buildOrderDetails($offer);
        if ($unmatched !== []) {
            return [
                'updated' => false,
                'order_id' => (int) $order->id,
                'message' => 'تم حفظ العرض. توجد أصناف غير موجودة في النظام.',
                'unmatched_products' => $unmatched,
                'needs_product_link' => true,
            ];
        }

        if ($details === []) {
            throw new \InvalidArgumentException('لا توجد بنود صالحة لتحديث الطلب.');
        }

        $userId = (int) (Auth::id() ?? 0);

        VatCalculator::applyToOffer($offer);

        $productsTotal = round(array_sum(array_map(
            static fn ($l) => (float) ($l['total'] ?? 0),
            $details
        )), 3);
        $shippingCost = round((float) ($offer->transportation ?? $order->shipping_cost ?? 0), 3);
        $vat = VatCalculator::amount($productsTotal, (float) ($offer->vat ?? $order->vat ?? 0) > 0);
        $prepaid = round((float) ($order->prepaid_amount ?? 0), 3);
        $discount = round((float) ($order->discount ?? 0), 3);
        $netTotal = round($productsTotal + $shippingCost + $vat - $discount - $prepaid, 3);

        $oldDebtAmount = round((float) ($offer->debt_amount ?: 0), 3);
        $newDebtAmount = round((float) ($offer->total ?? 0), 3);
        $debtDelta = round($newDebtAmount - $oldDebtAmount, 3);
        $debtAdjusted = false;

        DB::transaction(function () use (
            $offer,
            $order,
            $details,
            $userId,
            $productsTotal,
            $shippingCost,
            $vat,
            $netTotal,
            $debtDelta,
            $newDebtAmount,
            &$debtAdjusted
        ) {
            OrderProduct::where('order_id', $order->id)->delete();

            $insertData = [];
            foreach ($details as $od) {
                $insertData[] = [
                    'order_id' => $order->id,
                    'category_id' => (int) $od['category_id'],
                    'quantity' => (float) $od['quantity'],
                    'price' => (float) $od['price'],
                    'total_price' => (float) $od['total'],
                    'special_details' => $od['special_details'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            OrderProduct::insert($insertData);

            $order->total_invoice = $productsTotal;
            $order->sales = $productsTotal;
            $order->vat = $vat;
            $order->shipping_cost = $shippingCost;
            $order->shipping_revenue = $shippingCost;
            $order->net_total = $netTotal;

            $quoteName = trim((string) ($offer->quote ?? ''));
            if ($quoteName !== '') {
                $order->customer_name = $quoteName;
            }
            $phone = trim((string) ($offer->client_phone ?? ''));
            if ($phone !== '') {
                $order->customer_phone_1 = $phone;
            }

            $order->save();
            $offer->save();

            if (abs($debtDelta) >= 0.01 && $offer->debt_posted_at !== null && $offer->customer_company_id) {
                $company = customerCompany::find($offer->customer_company_id);
                if ($company) {
                    $detailsTxt = 'تعديل مديونية عرض سعر رقم ' . $offer->id
                        . ' بعد تحديث الطلب رقم ' . $order->id;
                    DB::statement('CALL update_customer_company_balance(?, ?, ?, ?, ?, ?, ?, ?)', [
                        $company->id,
                        $debtDelta,
                        null,
                        (int) $offer->id,
                        $detailsTxt,
                        'عروض أسعار',
                        $userId,
                        now(),
                    ]);
                    $this->offerDebtAccounting->adjustOfferDebt(
                        $offer,
                        $company,
                        (float) $debtDelta,
                        $userId
                    );
                    $offer->debt_amount = $newDebtAmount;
                    $offer->save();
                    $debtAdjusted = true;
                }
            }

            DB::table('trackings')->insert([
                'order_id' => $order->id,
                'action' => 'تحديث الطلب من تعديل عرض السعر #' . $offer->id,
                'date' => now()->toDateString(),
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('notes')->insert([
                'order_id' => $order->id,
                'user_id' => $userId,
                'note' => 'تم تحديث أصناف وإجماليات الطلب من تعديل عرض السعر #' . $offer->id,
                'added_from' => 'تعديل عرض سعر',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $message = 'تم تعديل العرض وتحديث الطلب رقم ' . $order->id;
        if ($debtAdjusted) {
            $message .= ' مع تسوية فرق المديونية';
        }

        return [
            'updated' => true,
            'order_id' => (int) $order->id,
            'debt_adjusted' => $debtAdjusted,
            'message' => $message,
        ];
    }

    /**
     * @return array{0: list<array{category_id: int, quantity: float, price: float, total: float, special_details: ?string}>, 1: list<string>}
     */
    private function buildOrderDetails(Offers $offer): array
    {
        $analysis = $this->productMatcher->analyze($offer);
        $byLineId = [];
        foreach ($analysis['lines'] as $row) {
            $byLineId[(int) $row['offer_line_id']] = $row;
        }

        $details = [];
        $unmatched = [];

        foreach ($offer->category as $line) {
            $name = trim((string) ($line->category_name ?? ''));
            if ($name === '') {
                continue;
            }

            $gap = $byLineId[(int) $line->id] ?? null;
            $catId = (int) ($gap['category']['id'] ?? $line->matched_category_id ?? 0);
            if ($catId <= 0) {
                $unmatched[] = $name;
                continue;
            }

            $qty = (float) ($line->category_quantity ?? 0);
            if ($qty <= 0) {
                throw new \InvalidArgumentException(
                    'الكمية يجب أن تكون أكبر من صفر للصنف «' . $name . '»'
                );
            }

            $price = (float) ($line->new_category_price ?? 0);
            $total = (float) ($line->total_price ?? ($qty * $price));

            $details[] = [
                'category_id' => $catId,
                'quantity' => $qty,
                'price' => $price,
                'total' => $total,
                'special_details' => $line->description ?: null,
            ];
        }

        return [$details, $unmatched];
    }
}
