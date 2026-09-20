<?php

namespace App\Enums;

enum ShippingSizeTier: string
{
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';

    public function labelAr(): string
    {
        return match ($this) {
            self::Small => 'صغير',
            self::Medium => 'متوسط',
            self::Large => 'كبير',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Small => 1,
            self::Medium => 2,
            self::Large => 3,
        };
    }

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom(strtolower(trim($value)));
    }

    /**
     * @param  self[]  $tiers
     */
    public static function maxOf(array $tiers): self
    {
        if ($tiers === []) {
            return self::Large;
        }

        usort($tiers, fn (self $a, self $b) => $b->rank() <=> $a->rank());

        return $tiers[0];
    }
}
