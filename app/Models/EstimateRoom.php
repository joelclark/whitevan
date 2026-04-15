<?php

namespace App\Models;

use Database\Factories\EstimateRoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'estimate_id',
    'name',
    'page',
    'sqft',
    'linear_feet',
    'position',
])]
class EstimateRoom extends Model
{
    /** @use HasFactory<EstimateRoomFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page' => 'integer',
            'sqft' => 'integer',
            'linear_feet' => 'integer',
            'position' => 'integer',
        ];
    }

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }
}
