<?php

namespace App\Enums;

enum LineItemKind: string
{
    case Material = 'material';
    case Labor = 'labor';

    public function label(): string
    {
        return match ($this) {
            self::Material => 'Material',
            self::Labor => 'Labor',
        };
    }
}
