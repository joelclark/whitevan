<?php

namespace App\Enums;

enum QuoteStatus: string
{
    case Sent = 'sent';

    public function label(): string
    {
        return match ($this) {
            self::Sent => 'Sent',
        };
    }
}
