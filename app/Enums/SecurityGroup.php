<?php

namespace App\Enums;

enum SecurityGroup: string
{
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Full access to all account features',
        };
    }

    /**
     * @return array<string>
     */
    public function abilities(): array
    {
        return match ($this) {
            self::Admin => ['*'],
        };
    }

    /**
     * @return array<int, array{value: string, label: string, description: string, abilities: array<string>}>
     */
    public static function toArray(): array
    {
        return array_map(fn (self $group) => [
            'value' => $group->value,
            'label' => $group->label(),
            'description' => $group->description(),
            'abilities' => $group->abilities(),
        ], self::cases());
    }
}
