<?php

namespace App\Enums;

enum Trade: string
{
    case Flooring = 'flooring';

    public function label(): string
    {
        return match ($this) {
            self::Flooring => 'Flooring',
        };
    }
}
