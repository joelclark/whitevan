<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'kind',
    'material_deposit_percent',
    'labor_deposit_percent',
])]
class DepositDefaults extends Model
{
    public const DEFAULT_KIND = 'default';

    protected $table = 'deposit_defaults';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'material_deposit_percent' => 'integer',
            'labor_deposit_percent' => 'integer',
        ];
    }

    public static function default(): self
    {
        return static::query()->where('kind', self::DEFAULT_KIND)->firstOrFail();
    }
}
