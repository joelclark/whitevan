<?php

namespace App\Models;

use Database\Factories\ContractTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'kind',
    'label',
    'body',
])]
class ContractTemplate extends Model
{
    /** @use HasFactory<ContractTemplateFactory> */
    use HasFactory;

    public const DEFAULT_KIND = 'default';

    public static function default(): self
    {
        return static::query()->where('kind', self::DEFAULT_KIND)->firstOrFail();
    }
}
