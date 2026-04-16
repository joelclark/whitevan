<?php

namespace App\Enums;

enum LineItemCategory: string
{
    case Demo = 'demo';
    case Prep = 'prep';
    case Install = 'install';
    case Trim = 'trim';
    case Services = 'services';

    public function label(): string
    {
        return match ($this) {
            self::Demo => 'Demo',
            self::Prep => 'Prep',
            self::Install => 'Install',
            self::Trim => 'Trim',
            self::Services => 'Services',
        };
    }
}
