<?php

namespace App\Enums;

enum OrderDeliveryStatus: string
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Refused = 'refused';
    case Cancelled = 'cancelled';

    public function labelAr(): string
    {
        return match ($this) {
            self::Pending => 'في الانتظار',
            self::Partial => 'شحن جزئي',
            self::Shipped => 'تم الشحن',
            self::Delivered => 'تم التسليم',
            self::Refused => 'رفض استلام',
            self::Cancelled => 'ملغي',
        };
    }
}
