<?php

namespace App\Enums;

enum SettlementBatchStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Cancelled = 'cancelled';

    public function labelAr(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Posted => 'مُرحّل',
            self::Cancelled => 'ملغي',
        };
    }
}
