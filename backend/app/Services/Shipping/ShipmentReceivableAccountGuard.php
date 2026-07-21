<?php

namespace App\Services\Shipping;

use App\Enums\CollectionProviderType;
use App\Models\CollectionCompany;
use App\Models\Order;
use App\Models\ShippingCompany;
use App\Support\CollectionProviderMorph;
use App\Services\Accounting\ReceivableTreeAccountGuard;

/**
 * يضمن قبل «تم التسليم» / «نقل الذمة» أن جهات التحصيل/الشحن التي ستحمل مديونية
 * العميل مرتبطة بحساب ذمم في شجرة الحسابات.
 *
 * عند الشحن لا تُرمى مديونية على هذه الجهات — يُحفظ التعريف فقط.
 * عند تأكيد التسليم تنتقل ذمة العميل إلى شركة الشحن/المندوب (جزء COD)
 * وإلى شركة التحصيل (الجزء المدفوع مقدماً) عبر
 * {@see \App\Services\Accounting\DeliveryConfirmationAccountingService}.
 * إذا لم تكن الجهة مرتبطة بحساب فلن ينتقل القيد وتبقى المديونية على العميل
 * بشكل خاطئ. لذلك نُلزم بربط الحساب عند التسليم/نقل الذمة (وليس عند الشحن).
 */
class ShipmentReceivableAccountGuard
{
    public function __construct(
        private CollectionReceivableAccountResolver $resolver,
        private ReceivableTreeAccountGuard $receivableGuard,
    ) {
    }

    /**
     * يتحقق من ربط الحسابات عند تأكيد التسليم / نقل الذمة (حيث تُرمى المديونية فعلياً).
     * يقرأ الجهات والمبالغ من تفاصيل الطلب المحفوظة عند الشحن.
     */
    public function assertLinkedForDelivery(Order $order): void
    {
        $order->loadMissing('order_details');
        $od = $order->order_details;
        if (! $od) {
            return;
        }

        $shippingCompanyId = (int) ($od->shipping_provider_id ?? $od->shipping_company_id ?? 0);
        if ($shippingCompanyId <= 0) {
            return;
        }

        $this->assertLinkedForShip(
            $order,
            $shippingCompanyId,
            $od->collection_provider_type,
            $od->collection_provider_id ? (int) $od->collection_provider_id : null,
            $od->collection_company_id ? (int) $od->collection_company_id : null,
            $od->shipping_receivable_amount !== null ? (float) $od->shipping_receivable_amount : null,
            $od->collection_receivable_amount !== null ? (float) $od->collection_receivable_amount : null,
            null,
        );
    }

    /**
     * يتحقق من ربط الحسابات قبل رمي المديونية (تسليم / نقل ذمة). يرمي
     * {@see UnlinkedReceivableAccountException} برسالة عربية واضحة عند وجود جهة غير مرتبطة.
     */
    public function assertLinkedForShip(
        Order $order,
        int $shippingCompanyId,
        ?string $collectionProviderType,
        ?int $collectionProviderId,
        ?int $legacyCollectionCompanyId,
        ?float $manualShippingAmount,
        ?float $manualCollectionAmount,
        ?float $basisNetTotal = null,
    ): void {
        $net = round(max(0, $basisNetTotal ?? (float) $order->net_total), 2);
        if ($net <= 0.009) {
            return;
        }

        $prepaid = round(max(0, (float) ($order->prepaid_amount ?? 0)), 2);

        $prepaidPart = round(min($prepaid, $net), 2);
        $codPart = round(max(0, $net - $prepaidPart), 2);

        if ($manualShippingAmount !== null || $manualCollectionAmount !== null) {
            $codPart = round((float) ($manualShippingAmount ?? 0), 2);
            $prepaidPart = round((float) ($manualCollectionAmount ?? 0), 2);
        }

        [$provType, $provId] = $this->resolveCollectionProvider(
            $shippingCompanyId,
            $collectionProviderType,
            $collectionProviderId,
            $legacyCollectionCompanyId,
            $net,
            $prepaid,
        );

        $missing = [];

        // جزء الدفع عند الاستلام (COD) ينتقل إلى شركة الشحن/المندوب.
        if ($codPart > 0.009) {
            $sc = ShippingCompany::find($shippingCompanyId);
            $shipReceivableId = $sc?->receivable_tree_account_id
                ? (int) $sc->receivable_tree_account_id
                : null;

            if (! $sc || ! $this->receivableGuard->sanitizeReceivableAccountId($shipReceivableId)) {
                $name = $sc?->name ?? ('#' . $shippingCompanyId);
                if ($shipReceivableId && $this->receivableGuard->isPaymentSourceTreeAccount($shipReceivableId)) {
                    $missing[] = 'شركة الشحن «' . $name
                        . '» مربوطة بحساب خزينة/بنك — يجب ربطها بحساب ذمم منفصل في شجرة الحسابات.';
                } else {
                    $missing[] = 'شركة الشحن «' . $name . '» غير مرتبطة بحساب ذمم في شجرة الحسابات.';
                }
            }
        }

        // الجزء المدفوع مقدماً ينتقل إلى جهة التحصيل (إن اختلفت عن شركة الشحن).
        $collectionIsShippingCompany = in_array($provType, [
            CollectionProviderType::ShippingCompany->value,
            CollectionProviderType::Courier->value,
        ], true) && (int) $provId === $shippingCompanyId;

        $checkableCollectionTypes = [
            CollectionProviderType::ShippingCompany->value,
            CollectionProviderType::Courier->value,
            CollectionProviderType::CollectionCompany->value,
        ];

        if (
            $prepaidPart > 0.009
            && $provType
            && $provId
            && in_array($provType, $checkableCollectionTypes, true)
            && ! $collectionIsShippingCompany
        ) {
            $accId = $this->resolver->receivableAccountIdForProvider($provType, (int) $provId);
            if (! $accId) {
                $name = CollectionProviderMorph::resolveName($provType, (int) $provId) ?? ('#' . $provId);
                $rawId = $this->rawReceivableAccountIdForProvider($provType, (int) $provId);
                $label = $provType === CollectionProviderType::CollectionCompany->value
                    ? 'شركة التحصيل'
                    : 'جهة التحصيل';
                if ($rawId && $this->receivableGuard->isPaymentSourceTreeAccount($rawId)) {
                    $missing[] = $label . ' «' . $name
                        . '» مربوطة بحساب خزينة/بنك — يجب ربطها بحساب ذمم منفصل في شجرة الحسابات.';
                } else {
                    $missing[] = $label . ' «' . $name
                        . '» غير مرتبطة بحساب ذمم في شجرة الحسابات (اربطها هي أو شركة الشحن المرتبطة بها).';
                }
            }
        }

        if (! empty($missing)) {
            throw new UnlinkedReceivableAccountException(
                'لا يمكن إتمام التسليم/نقل الذمة قبل ربط الحسابات حتى تُسجَّل المديونيات بشكل صحيح. '
                . implode(' ', $missing)
            );
        }
    }

    /**
     * يحدد جهة التحصيل الفعلية بنفس منطق
     * {@see OrderFinancialStateService::inferCollectionProviderOnShip}.
     *
     * @return array{0: ?string, 1: ?int}
     */
    private function resolveCollectionProvider(
        int $shippingCompanyId,
        ?string $explicitType,
        ?int $explicitId,
        ?int $legacyCollectionCompanyId,
        float $net,
        float $prepaid,
    ): array {
        if ($explicitType && $explicitId) {
            return [$explicitType, $explicitId];
        }

        if ($legacyCollectionCompanyId) {
            return [CollectionProviderType::ShippingCompany->value, $legacyCollectionCompanyId];
        }

        if ($prepaid >= $net - 0.02) {
            return [CollectionProviderType::None->value, null];
        }

        $sc = ShippingCompany::find($shippingCompanyId);
        $type = ($sc && $sc->type === 'مندوب')
            ? CollectionProviderType::Courier->value
            : CollectionProviderType::ShippingCompany->value;

        return [$type, $shippingCompanyId];
    }

    private function rawReceivableAccountIdForProvider(string $type, int $id): ?int
    {
        $enum = CollectionProviderType::tryFrom($type);
        if (! $enum) {
            return null;
        }

        return match ($enum) {
            CollectionProviderType::CollectionCompany => CollectionCompany::find($id)?->receivable_tree_account_id,
            CollectionProviderType::ShippingCompany, CollectionProviderType::Courier => ShippingCompany::find($id)?->receivable_tree_account_id,
            default => null,
        };
    }
}
