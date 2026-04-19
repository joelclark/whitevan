<?php

namespace App\Models;

use App\Enums\LineItemCategory;
use App\Enums\LineItemKind;
use App\Enums\LineItemUnit;
use Database\Factories\EstimateLineItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'estimate_id',
    'key',
    'label',
    'category',
    'kind',
    'quantity',
    'unit',
    'unit_price',
    'notes',
    'position',
    'deprecated_at',
])]
class EstimateLineItem extends Model
{
    /** @use HasFactory<EstimateLineItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => LineItemCategory::class,
            'kind' => LineItemKind::class,
            'unit' => LineItemUnit::class,
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'position' => 'integer',
            'deprecated_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('deprecated_at');
    }

    public function isDeprecated(): bool
    {
        return $this->deprecated_at !== null;
    }

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }
}
