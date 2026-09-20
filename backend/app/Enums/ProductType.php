<?php

namespace App\Enums;

enum ProductType: string
{
    case RawMaterial = 'raw_material';
    case SemiFinished = 'semi_finished';
    case Finished = 'finished';

    public function label(): string
    {
        return match ($this) {
            self::RawMaterial => 'Raw material',
            self::SemiFinished => 'Semi-finished (WIP)',
            self::Finished => 'Finished good',
        };
    }
}
