<?php

namespace App\Services\Offers;

use App\Models\Category;
use App\Models\Offers;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\OrderProduct;
use App\Models\OrderSource;
use App\Models\ShippingMethod;
use App\Models\customerCompany;
use App\Services\Accounting\AccountLinkingService;
use App\Services\Accounting\OfferDebtAccountingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OfferConvertToOrderService
{
    public const DELIVERY_MODES = [
        'later' => 'الشحن لاحقاً / يُحدد عند التنفيذ',
        'self_pickup' => 'استلام ذاتي من العميل',
        'client_rep' => 'مندوب تابع للعميل',
        'system' => 'شحن عبر السيستم',
    ];

    public function __construct(
        private AccountLinkingService $accountLinking,
        private OfferDebtAccountingService $offerDebtAccounting,
        private OfferProductMatchService $productMatcher,
    ) {}

    /**
     * @param  array<string,mixed>  $payload
     * @return array{order: Order, offer: Offers, debt_posted_now: bool}
     */
    public function convert(Offers $offer, array $payload): array
    {
        if ($offer->converted_order_id) {
            throw new \InvalidArgumentException(
                'تم تحويل هذا العرض مسبقاً إلى الطلب رقم ' . $offer->converted_order_id
            );
        }

        $offer->loadMissing(['category', 'customerCompany']);

        $companyId = (int) ($payload['customer_company_id'] ?? $offer->customer_company_id ?? 0);
        if ($companyId <= 0) {
            throw new \InvalidArgumentException('يجب اختيار عميل شركة قبل التحويل إلى طلب.');
        }

        $company = customerCompany::find($companyId);
        if (! $company) {
            throw new \InvalidArgumentException('عميل الشركة غير موجود.');
        }

        $details = $payload['order_details'] ?? [];
        if (! is_array($details) || $details === []) {
            throw new \InvalidArgumentException('تفاصيل أصناف الطلب مطلوبة.');
        }

        foreach ($details as $i => $line) {
            $catId = (int) ($line['category_id'] ?? 0);
            if ($catId <= 0 || ! Category::query()->whereKey($catId)->exists()) {
                throw new \InvalidArgumentException('صنف غير صالح في السطر رقم ' . ($i + 1) . ' — طابق المنتجات أولاً.');
            }
            if ((float) ($line['quantity'] ?? 0) <= 0) {
                throw new \InvalidArgumentException('الكمية يجب أن تكون أكبر من صفر في السطر ' . ($i + 1));
            }
        }

        $userId = (int) (Auth::id() ?? 0);
        $deliveryMode = (string) ($payload['delivery_mode'] ?? 'later');
        if (! array_key_exists($deliveryMode, self::DELIVERY_MODES)) {
            $deliveryMode = 'later';
        }

        $shippingNote = trim((string) ($payload['shipping_note'] ?? ''));
        $orderNotes = trim((string) ($payload['order_notes'] ?? ''));
        $deliveryLabel = self::DELIVERY_MODES[$deliveryMode];
        $combinedNote = trim(
            'تحويل من عرض سعر #' . $offer->id
            . ' | طريقة التسليم: ' . $deliveryLabel
            . ($shippingNote !== '' ? ' | ملاحظة الشحن: ' . $shippingNote : '')
            . ($orderNotes !== '' ? ' | ' . $orderNotes : '')
        );

        $totalInvoice = round((float) ($payload['total_invoice'] ?? $offer->total ?? 0), 3);
        $shippingCost = round((float) ($payload['shipping_cost'] ?? 0), 3);
        $prepaid = round((float) ($payload['prepaid_amount'] ?? 0), 3);
        $discount = round((float) ($payload['discount'] ?? 0), 3);
        $vat = round((float) ($payload['vat'] ?? $offer->vat ?? 0), 3);
        $netTotal = round((float) ($payload['net_total'] ?? ($totalInvoice + $shippingCost + $vat - $discount - $prepaid)), 3);

        $debtPostedNow = false;

        $order = DB::transaction(function () use (
            $offer,
            $company,
            $payload,
            $details,
            $userId,
            $combinedNote,
            $totalInvoice,
            $shippingCost,
            $prepaid,
            $discount,
            $vat,
            $netTotal,
            &$debtPostedNow
        ) {
            // ربط العميل + ترحيل المديونية مرة واحدة إن لم تُرحَّل من العرض
            if ((int) $offer->customer_company_id !== (int) $company->id) {
                $offer->customer_company_id = $company->id;
            }
            if (trim((string) $offer->quote) === '') {
                $offer->quote = $company->name;
            }

            $this->accountLinking->ensureCustomerCompanyAccount($company);
            $company->refresh();

            if ($offer->debt_posted_at === null) {
                $amount = round((float) ($offer->total ?: $totalInvoice), 3);
                if ($amount <= 0) {
                    throw new \InvalidArgumentException('لا يمكن ترحيل مديونية لأن إجمالي العرض صفر.');
                }

                $detailsTxt = 'مديونية من عرض سعر رقم ' . $offer->id . ' (عند التحويل لطلب)';
                DB::statement('CALL update_customer_company_balance(?, ?, ?, ?, ?, ?, ?, ?)', [
                    $company->id,
                    $amount,
                    null,
                    (int) $offer->id,
                    $detailsTxt,
                    'عروض أسعار',
                    $userId,
                    now(),
                ]);
                $this->offerDebtAccounting->postOfferDebt($offer, $company, (float) $amount, $userId);
                $offer->debt_amount = $amount;
                $offer->debt_posted_at = now();
                $debtPostedNow = true;
            }

            $offer->save();

            $shippingMethodId = (int) ($payload['shipping_method_id'] ?? 0);
            if ($shippingMethodId <= 0) {
                $shippingMethodId = (int) (ShippingMethod::query()->orderBy('id')->value('id') ?? 0);
            }
            if ($shippingMethodId <= 0) {
                throw new \InvalidArgumentException('لا توجد طريقة شحن افتراضية في النظام.');
            }

            $orderSourceId = (int) ($payload['order_source_id'] ?? 0);
            if ($orderSourceId <= 0) {
                $orderSourceId = (int) (OrderSource::query()->orderBy('id')->value('id') ?? 0);
            }
            if ($orderSourceId <= 0) {
                throw new \InvalidArgumentException('لا يوجد مصدر طلب افتراضي في النظام.');
            }

            $phone = trim((string) ($payload['customer_phone_1'] ?? ''));
            if ($phone === '') {
                $phone = trim((string) ($company->phone1 ?? $offer->client_phone ?? '0000000000'));
            }

            // أعمدة orders غير قابلة لـ NULL في المخطط القديم (phone2 / tel / …)
            $phone2 = trim((string) ($payload['customer_phone_2'] ?? $company->phone2 ?? ''));
            $tel = trim((string) ($payload['tel'] ?? $company->tel ?? ''));
            $city = trim((string) ($payload['city'] ?? $company->city ?? ''));
            $address = trim((string) ($payload['address'] ?? $company->address ?? ''));
            if ($address === '') {
                $address = 'غير محدد';
            }
            $governorate = trim((string) ($payload['governorate'] ?? $company->governorate ?? ''));
            if ($governorate === '') {
                $governorate = 'غير محدد';
            }

            $order = Order::create([
                'customer_name' => $payload['customer_name'] ?? $company->name,
                'customer_type' => 'شركة',
                'customer_phone_1' => $phone,
                'customer_phone_2' => $phone2,
                'tel' => $tel,
                'governorate' => $governorate,
                'city' => $city !== '' ? $city : null,
                'address' => $address,
                'order_date' => $payload['order_date'] ?? now()->toDateString(),
                'delivery_date' => $payload['delivery_date'] ?? null,
                'shipping_method_id' => $shippingMethodId,
                'order_source_id' => $orderSourceId,
                'order_type' => $payload['order_type'] ?? 'جديد',
                'shipping_cost' => $shippingCost,
                'shipping_revenue' => $shippingCost,
                'total_invoice' => $totalInvoice,
                'prepaid_amount' => $prepaid,
                'discount' => $discount,
                'net_total' => $netTotal,
                'vat' => $vat,
                'sales' => array_sum(array_map(fn ($l) => (float) ($l['total'] ?? 0), $details)),
                'company_id' => $company->id,
                'offer_id' => $offer->id,
                'offer_debt_posted' => true,
            ]);

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
            OrderDetails::updateOrCreate(['order_id' => $order->id]);

            DB::table('customer_companies')->where('id', $company->id)->increment('number_of_orders', 1);

            DB::table('trackings')->insert([
                'order_id' => $order->id,
                'action' => 'طلب جديد — تحويل من عرض سعر #' . $offer->id,
                'date' => now()->toDateString(),
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($combinedNote !== '') {
                DB::table('notes')->insert([
                    'order_id' => $order->id,
                    'user_id' => $userId,
                    'note' => $combinedNote,
                    'added_from' => 'تحويل عرض سعر',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $offer->converted_order_id = $order->id;
            $offer->converted_at = now();
            $offer->save();

            return $order;
        });

        return [
            'order' => $order,
            'offer' => $offer->fresh(['customerCompany', 'category']),
            'debt_posted_now' => $debtPostedNow,
        ];
    }
}
