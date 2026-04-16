<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\EstimateStatus;
use App\Enums\FloorplanAssetsStatus;
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

    'agent_errors',
    'debug_log',
    'floorplan_assets_status',
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
            'floorplan_assets_status' => FloorplanAssetsStatus::class,
            'total_sqft' => 'integer',
            'interview_answers' => AsArrayObject::class,

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

            // Floorplan PNGs follow the same disposable policy as the PDF —
            // remove the rendered images on (soft) delete to avoid orphaned
            // files. The DB rows are left in place by SoftDeletes; cascade
            // would only fire on a hard delete.
            $imagePaths = $estimate->floorplanPages()->pluck('image_path')->all();
            if ($imagePaths !== []) {
                Storage::disk('local')->delete($imagePaths);
            }

            $estimate->lineItems()->delete();
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

    /**
     * @return HasMany<EstimateLineItem, $this>
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(EstimateLineItem::class)->orderBy('position');
    }

    /**
     * @return HasMany<EstimateLineItem, $this>
     */
    public function activeLineItems(): HasMany
    {
        return $this->hasMany(EstimateLineItem::class)
            ->whereNull('deprecated_at')
            ->orderBy('position');
    }

    /**
     * @return HasMany<EstimateFloorplanPage, $this>
     */
    public function floorplanPages(): HasMany
    {
        return $this->hasMany(EstimateFloorplanPage::class)->orderBy('page');
    }
}
