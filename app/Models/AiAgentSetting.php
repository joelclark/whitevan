<?php

namespace App\Models;

use App\Enums\AiAgentKind;
use Database\Factories\AiAgentSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'kind',
    'label',
    'description',
    'system_prompt',
])]
class AiAgentSetting extends Model
{
    /** @use HasFactory<AiAgentSettingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => AiAgentKind::class,
        ];
    }

    public static function forKind(AiAgentKind $kind): self
    {
        return static::query()->where('kind', $kind->value)->firstOrFail();
    }
}
