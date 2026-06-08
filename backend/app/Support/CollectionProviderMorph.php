<?php

namespace App\Support;

use App\Enums\CollectionProviderType;
use App\Models\CollectionCompany;
use App\Models\ShippingCompany;
use App\Models\User;

/**
 * Resolves polymorphic collection provider to display name and operational shipping_company id.
 */
class CollectionProviderMorph
{
    public static function resolveName(?string $type, ?int $id): ?string
    {
        if (! $type || ! $id) {
            return null;
        }

        $enum = CollectionProviderType::tryFrom($type);
        if (! $enum) {
            return null;
        }

        return match ($enum) {
            CollectionProviderType::ShippingCompany, CollectionProviderType::Courier => ShippingCompany::find($id)?->name,
            CollectionProviderType::CollectionCompany => CollectionCompany::find($id)?->name,
            CollectionProviderType::Employee => User::find($id)?->name,
            CollectionProviderType::None => null,
        };
    }

    /**
     * ID usable with shipping_company_procedure (legacy operational ledger).
     */
    public static function operationalShippingCompanyId(?string $type, ?int $id): ?int
    {
        if (! $type || ! $id) {
            return null;
        }

        $enum = CollectionProviderType::tryFrom($type);
        if (! $enum) {
            return null;
        }

        if ($enum->usesShippingCompanyLedger()) {
            return $id;
        }

        if ($enum === CollectionProviderType::CollectionCompany) {
            $cc = CollectionCompany::find($id);

            return $cc?->linked_shipping_company_id ? (int) $cc->linked_shipping_company_id : null;
        }

        return null;
    }

    /**
     * Legacy collection_company_id on order_details (shipping_companies FK).
     */
    public static function legacyCollectionCompanyId(?string $type, ?int $id): ?int
    {
        if (! $type || ! $id) {
            return null;
        }

        $enum = CollectionProviderType::tryFrom($type);
        if (! $enum) {
            return null;
        }

        if ($enum === CollectionProviderType::CollectionCompany) {
            $cc = CollectionCompany::find($id);

            return $cc?->linked_shipping_company_id ? (int) $cc->linked_shipping_company_id : null;
        }

        if ($enum->usesShippingCompanyLedger()) {
            return $id;
        }

        return null;
    }
}
