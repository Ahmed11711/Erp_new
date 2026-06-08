<?php

namespace App\Enums;

enum CollectionProviderType: string
{
    case None = 'none';
    case ShippingCompany = 'shipping_company';
    case Courier = 'courier';
    case CollectionCompany = 'collection_company';
    case Employee = 'employee';

    public function labelAr(): string
    {
        return match ($this) {
            self::None => 'لا يوجد تحصيل',
            self::ShippingCompany => 'شركة شحن',
            self::Courier => 'مندوب',
            self::CollectionCompany => 'شركة تحصيل',
            self::Employee => 'موظف',
        };
    }

    /** Types that use shipping_companies.id for operational procedure. */
    public function usesShippingCompanyLedger(): bool
    {
        return in_array($this, [self::ShippingCompany, self::Courier], true);
    }
}
