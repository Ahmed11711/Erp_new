<?php

namespace App\Services\Shipping;

use App\Enums\CollectionProviderType;
use App\Models\CollectionCompany;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\ShippingCompany;
use App\Services\Accounting\ReceivableTreeAccountGuard;

/**
 * يحدد حساب الذمم (شجرة الحسابات) لجهة التحصيل من الربط الجديد أو legacy.
 */
class CollectionReceivableAccountResolver
{
    public function __construct(
        private readonly ReceivableTreeAccountGuard $receivableGuard,
    ) {
    }

    public function receivableAccountIdForOrderDetails(?OrderDetails $od): ?int
    {
        if (! $od) {
            return null;
        }

        $type = $od->collection_provider_type;
        $id = $od->collection_provider_id ? (int) $od->collection_provider_id : null;

        if ($type && $id) {
            $accountId = $this->receivableAccountIdForProvider($type, $id);
            if ($accountId) {
                return $accountId;
            }
        }

        if ($od->collection_company_id) {
            $legacy = ShippingCompany::find($od->collection_company_id);

            return $this->receivableGuard->sanitizeReceivableAccountId(
                $legacy?->receivable_tree_account_id ? (int) $legacy->receivable_tree_account_id : null
            );
        }

        return null;
    }

    public function receivableAccountIdForProvider(string $type, int $id): ?int
    {
        $enum = CollectionProviderType::tryFrom($type);
        if (! $enum) {
            return null;
        }

        return match ($enum) {
            CollectionProviderType::CollectionCompany => $this->fromCollectionCompany($id),
            CollectionProviderType::ShippingCompany, CollectionProviderType::Courier => $this->fromShippingCompany($id),
            default => null,
        };
    }

    public function receivableAccountIdForOrder(Order $order): ?int
    {
        $order->loadMissing('order_details');

        return $this->receivableAccountIdForOrderDetails($order->order_details);
    }

    private function fromCollectionCompany(int $id): ?int
    {
        $cc = CollectionCompany::find($id);
        if (! $cc) {
            return null;
        }
        if ($cc->receivable_tree_account_id) {
            return $this->receivableGuard->sanitizeReceivableAccountId((int) $cc->receivable_tree_account_id);
        }

        if ($cc->linked_shipping_company_id) {
            return $this->fromShippingCompany((int) $cc->linked_shipping_company_id);
        }

        return null;
    }

    private function fromShippingCompany(int $id): ?int
    {
        $sc = ShippingCompany::find($id);

        return $this->receivableGuard->sanitizeReceivableAccountId(
            $sc?->receivable_tree_account_id ? (int) $sc->receivable_tree_account_id : null
        );
    }
}
