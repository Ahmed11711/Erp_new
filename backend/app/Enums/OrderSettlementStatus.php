<?php

namespace App\Enums;

enum OrderSettlementStatus: string
{
    case Open = 'open';
    case Partial = 'partial';
    case Settled = 'settled';
    case NotApplicable = 'not_applicable';

    public function labelAr(): string
    {
        return match ($this) {
            self::Open => 'مفتوح',
            self::Partial => 'تسوية جزئية',
            self::Settled => 'مُسوّى',
            self::NotApplicable => '—',
        };
    }
}
