<?php

namespace App\Enums;

enum OrderRollbackTarget: string
{
    case Confirmed = 'confirmed';
    case New = 'new';

    public function orderStatusLabel(): string
    {
        return match ($this) {
            self::Confirmed => 'طلب مؤكد',
            self::New => 'طلب جديد',
        };
    }

    public function displayLabel(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmed',
            self::New => 'New',
        };
    }
}
