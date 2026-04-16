<?php

namespace App\Enums;

enum LineItemUnit: string
{
    case Sqft = 'sqft';
    case LinearFeet = 'lf';
    case Each = 'each';

    public function label(): string
    {
        return match ($this) {
            self::Sqft => 'Square Feet',
            self::LinearFeet => 'Linear Feet',
            self::Each => 'Each',
        };
    }

    public function abbreviation(): string
    {
        return $this->value;
    }
}
