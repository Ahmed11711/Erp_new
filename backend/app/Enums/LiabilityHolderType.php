<?php

namespace App\Enums;

enum LiabilityHolderType: string
{
    case Customer = 'customer';
    case ShippingCompany = 'shipping_company';
    case Courier = 'courier';
    case CollectionCompany = 'collection_company';
    case Employee = 'employee';

    public function labelAr(): string
    {
        return match ($this) {
            self::Customer => 'العميل',
            self::ShippingCompany => 'شركة شحن',
            self::Courier => 'مندوب',
            self::CollectionCompany => 'شركة تحصيل',
            self::Employee => 'موظف',
        };
    }
}
