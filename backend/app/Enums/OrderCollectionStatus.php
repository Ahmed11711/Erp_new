<?php

namespace App\Enums;

enum OrderCollectionStatus: string
{
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case Partial = 'partial';
    case Collected = 'collected';
    case Transferred = 'transferred';
    case Refused = 'refused';

    public function labelAr(): string
    {
        return match ($this) {
            self::NotRequired => 'لا يحتاج تحصيل',
            self::Pending => 'بانتظار التحصيل',
            self::Partial => 'تحصيل جزئي',
            self::Collected => 'تم التحصيل',
            self::Transferred => 'نُقلت الذمة',
            self::Refused => 'مرفوض',
        };
    }
}
