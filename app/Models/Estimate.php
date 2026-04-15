<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\EstimateStatus;
use App\Enums\Trade;
use Database\Factories\EstimateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'customer_id',
    'trade',
    'title',
    'pdf_path',
    'pdf_original_filename',
    'total_sqft',
    'status',
    'interview_answers',
    'line_item_prices',
    'agent_errors',
    'debug_log',
])]
class Estimate extends Model
{
    /** @use HasFactory<EstimateFactory> */
    use BelongsToAccount, HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trade' => Trade::class,
            'status' => EstimateStatus::class,
            'total_sqft' => 'integer',
            'interview_answers' => AsArrayObject::class,
            'line_item_prices' => AsArrayObject::class,
            'agent_errors' => 'array',
            'debug_log' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Uploaded PDFs are disposable once the estimate is gone. Covers soft
        // deletes too — there's no workflow for restoring an estimate, so
        // keeping the orphaned file would only bloat disk.
        static::deleting(function (self $estimate): void {
            if ($estimate->pdf_path !== null && $estimate->pdf_path !== '') {
                Storage::disk('local')->delete($estimate->pdf_path);
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<EstimateRoom, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(EstimateRoom::class)->orderBy('position');
    }
}
