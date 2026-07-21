<?php

namespace App\Http\Controllers;

use App\Enums\CollectionProviderType;
use App\Enums\LiabilityHolderType;
use App\Enums\OrderCollectionStatus;
use App\Enums\OrderDeliveryStatus;
use App\Enums\OrderSettlementStatus;
use App\Models\CollectionCompany;
use App\Models\Order;
use App\Models\OrderLiabilityTransfer;
use App\Models\ShippingCompany;
use App\Models\TreeAccount;
use App\Services\Shipping\CollectionReceivableAccountResolver;
use App\Services\Shipping\OrderFinancialStateService;
use App\Services\Shipping\OrderLiabilityTransferService;
use App\Support\CollectionProviderMorph;
use Illuminate\Http\Request;

/**
 * Simple fulfillment API for order page (delivery / collection / financial sections).
 */
class OrderFulfillmentController extends Controller
{
    public function __construct(
        private OrderFinancialStateService $financialState,
        private OrderLiabilityTransferService $liabilityTransfer,
    ) {
    }

    public function show(int $id)
    {
        $order = Order::with([
            'order_details.shipping_company',
            'order_details.collection_company',
        ])->findOrFail($id);

        if ($order->order_details) {
            $this->financialState->syncFromOrder($order);
            $order->refresh()->load('order_details.shipping_company', 'order_details.collection_company');
        }

        $od = $order->order_details;

        $suggestedHolder = $this->liabilityTransfer->resolveManualTransferHolder($order);
        if ($suggestedHolder) {
            $order->refresh()->load('order_details.shipping_company', 'order_details.collection_company');
            $od = $order->order_details;
        }

        $collectionName = CollectionProviderMorph::resolveName(
            $od?->collection_provider_type,
            $od?->collection_provider_id ? (int) $od->collection_provider_id : null
        );

        $liabilityName = null;
        if ($od?->liability_holder_type && $od->liability_holder_id) {
            $liabilityName = CollectionProviderMorph::resolveName(
                $this->liabilityToProviderType($od->liability_holder_type),
                (int) $od->liability_holder_id
            ) ?? $od->liability_holder_type;
        }

        $transfers = OrderLiabilityTransfer::where('order_id', $id)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $receivableAccId = app(CollectionReceivableAccountResolver::class)
            ->receivableAccountIdForOrderDetails($od);
        $receivableAccount = $receivableAccId ? TreeAccount::find($receivableAccId) : null;

        $showCollectionPicker = $this->shouldShowCollectionPicker($order);
        $isPrepaidCollection = $this->liabilityTransfer->isPrepaidCollectionOrder($order, $od);

        return response()->json([
            'order_id' => $order->id,
            'order_status' => $order->order_status,
            'delivery' => [
                'shipping_provider_id' => $od?->shipping_provider_id ?? $od?->shipping_company_id,
                'shipping_provider_name' => $od?->shipping_company?->name,
                'delivery_status' => $od?->delivery_status,
                'delivery_status_label' => OrderDeliveryStatus::tryFrom($od?->delivery_status ?? '')?->labelAr(),
                'delivery_date' => $od?->delivery_date,
                'shipping_date' => $od?->shipping_date,
            ],
            'collection' => [
                'collection_provider_type' => $od?->collection_provider_type,
                'collection_provider_id' => $od?->collection_provider_id,
                'collection_provider_name' => $collectionName,
                'receivable_account_id' => $receivableAccId,
                'receivable_account_label' => $receivableAccount
                    ? trim(($receivableAccount->code ?? '') . ' — ' . $receivableAccount->name)
                    : null,
                'legacy_collection_company_id' => $od?->collection_company_id,
                'collection_status' => $od?->collection_status,
                'collection_status_label' => OrderCollectionStatus::tryFrom($od?->collection_status ?? '')?->labelAr(),
                'amount_to_collect' => $od?->amount_to_collect,
                'collection_receivable_amount' => $od?->collection_receivable_amount,
                'collection_date' => $od?->collection_date,
                'show_picker' => $showCollectionPicker,
            ],
            'financial' => [
                'total_amount' => $od?->total_amount ?? $order->net_total,
                'paid_amount' => $od?->paid_amount ?? $order->prepaid_amount,
                'remaining_amount' => $od?->remaining_amount,
                'collection_receivable_amount' => $od?->collection_receivable_amount,
                'prepaid_amount' => $order->prepaid_amount,
                'net_total' => $order->net_total,
                'settlement_status' => $od?->settlement_status,
                'settlement_status_label' => OrderSettlementStatus::tryFrom($od?->settlement_status ?? '')?->labelAr(),
                'liability_holder_type' => $od?->liability_holder_type,
                'liability_holder_id' => $od?->liability_holder_id,
                'liability_holder_name' => $liabilityName,
                'liability_transferred_at' => $od?->liability_transferred_at,
                'liability_transfer_amount' => $this->liabilityTransfer->resolveTransferAmount($order, $od),
                'can_transfer_liability' => $this->liabilityTransfer->canTransferLiability($order, $od),
                'is_prepaid_collection' => $isPrepaidCollection,
                'suggested_liability_holder_type' => $suggestedHolder[0]->value ?? null,
                'suggested_liability_holder_id' => $suggestedHolder[1] ?? null,
            ],
            'liability_transfers' => $transfers,
        ]);
    }

    public function assignProviders(Request $request, int $id)
    {
        $order = Order::with('order_details')->findOrFail($id);

        $data = $request->validate([
            'shipping_provider_id' => 'nullable|exists:shipping_companies,id',
            'collection_provider_type' => 'nullable|in:none,shipping_company,courier,collection_company,employee',
            'collection_provider_id' => 'nullable|integer|min:1',
        ]);

        $od = $order->order_details;
        if (! $od) {
            return response()->json(['message' => 'لا توجد تفاصيل للطلب'], 422);
        }

        if (! empty($data['shipping_provider_id'])) {
            $od->shipping_provider_id = (int) $data['shipping_provider_id'];
            $od->shipping_company_id = (int) $data['shipping_provider_id'];
        }

        if (! empty($data['collection_provider_type'])) {
            $type = $data['collection_provider_type'];
            if ($type === CollectionProviderType::None->value) {
                $od->collection_provider_type = $type;
                $od->collection_provider_id = null;
                $od->collection_company_id = null;
            } else {
                $this->validateProviderExists($type, (int) ($data['collection_provider_id'] ?? 0));
                $od->collection_provider_type = $type;
                $od->collection_provider_id = (int) $data['collection_provider_id'];
                $legacy = CollectionProviderMorph::legacyCollectionCompanyId($type, (int) $data['collection_provider_id']);
                $od->collection_company_id = $legacy;
            }
        }

        $this->financialState->syncFromOrder($order, $od);

        return $this->show($id);
    }

    public function transferLiability(Request $request, int $id)
    {
        $data = $request->validate([
            'to_holder_type' => 'required|in:shipping_company,courier,collection_company',
            'to_holder_id' => 'required|integer|min:1',
            'amount' => 'nullable|numeric|min:0.001',
            'reason' => 'nullable|string|max:255',
        ]);

        $order = Order::with('order_details')->findOrFail($id);
        $preferred = LiabilityHolderType::from($data['to_holder_type']);

        try {
            // اسمح باختيار شركة مختلفة عن المرتبطة بالطلب: نطبّقها على الطلب ثم ننقل الذمة
            // في معاملة واحدة (يُلغى تغيير الشركة إن فشل النقل).
            $result = \Illuminate\Support\Facades\DB::transaction(function () use ($order, $preferred, $data) {
                $this->liabilityTransfer->applyChosenHolder($order, $preferred, (int) $data['to_holder_id']);

                $resolved = $this->liabilityTransfer->resolveManualTransferHolder(
                    $order,
                    $preferred,
                    (int) $data['to_holder_id'],
                );
                if (! $resolved) {
                    throw new \RuntimeException(
                        $preferred === LiabilityHolderType::CollectionCompany
                            ? 'تعذر تحديد شركة التحصيل. اختر شركة تحصيل من القائمة أو راجع ربط Paymob/Shopify بشركات التحصيل.'
                            : 'حدد جهة الشحن أو المندوب أولاً من شاشة الشحن.'
                    );
                }

                [$holder, $holderId] = $resolved;

                return $this->liabilityTransfer->transfer(
                    $order,
                    $holder,
                    $holderId,
                    isset($data['amount']) ? (float) $data['amount'] : null,
                    $data['reason'] ?? null,
                );
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'success',
            'transfer_id' => $result['transfer']->id,
            'gl_batch' => $result['gl_batch'],
            'fulfillment' => json_decode($this->show($id)->getContent(), true),
        ]);
    }

    private function validateProviderExists(string $type, int $id): void
    {
        $enum = CollectionProviderType::from($type);
        match ($enum) {
            CollectionProviderType::ShippingCompany, CollectionProviderType::Courier => ShippingCompany::findOrFail($id),
            CollectionProviderType::CollectionCompany => CollectionCompany::findOrFail($id),
            CollectionProviderType::Employee => \App\Models\User::findOrFail($id),
            CollectionProviderType::None => null,
        };
    }

    private function liabilityToProviderType(string $holderType): ?string
    {
        return match ($holderType) {
            'shipping_company' => CollectionProviderType::ShippingCompany->value,
            'courier' => CollectionProviderType::Courier->value,
            'collection_company' => CollectionProviderType::CollectionCompany->value,
            default => null,
        };
    }

    /** إظهار اختيار شركة التحصيل: طلب مؤكد/جزئي + فرد + يوجد مبلغ للتحصيل. */
    private function shouldShowCollectionPicker(Order $order): bool
    {
        if ($order->customer_type === 'شركة') {
            return false;
        }
        if (! in_array($order->order_status, ['طلب مؤكد', 'شحن جزئي', 'طلب جديد'], true)) {
            return false;
        }
        $remaining = (float) $order->net_total - (float) ($order->prepaid_amount ?? 0);

        return $remaining > 0.009 || (float) ($order->prepaid_amount ?? 0) > 0.009;
    }
}
