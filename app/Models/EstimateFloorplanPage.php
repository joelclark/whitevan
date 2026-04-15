<?php

namespace App\Models;

use Database\Factories\EstimateFloorplanPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'estimate_id',
    'page',
    'image_path',
    'width',
    'height',
])]
class EstimateFloorplanPage extends Model
{
    /** @use HasFactory<EstimateFloorplanPageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }
}
