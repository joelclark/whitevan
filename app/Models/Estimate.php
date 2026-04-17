<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\EstimateStatus;
use App\Enums\FloorplanAssetsStatus;
use App\Enums\QuoteStatus;
use App\Enums\Trade;
use Carbon\CarbonInterface;
use Database\Factories\EstimateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'project_id',
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
    'quote_status',
    'quote_token',
    'quote_sent_at',
    'quote_customer_viewed_at',
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
            'quote_status' => QuoteStatus::class,
            'quote_sent_at' => 'datetime',
            'quote_customer_viewed_at' => 'datetime',
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

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Customer via the Project parent. Preserves `$estimate->customer`
     * reads and eager-loading now that the FK lives on Project.
     *
     * @return HasOneThrough<Customer, Project, $this>
     */
    public function customer(): HasOneThrough
    {
        return $this->hasOneThrough(
            Customer::class,
            Project::class,
            'id',
            'id',
            'project_id',
            'customer_id',
        );
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

    public function isQuoteSent(): bool
    {
        return $this->quote_status === QuoteStatus::Sent;
    }

    public function latestContentChange(): CarbonInterface
    {
        $lineItemMax = $this->activeLineItems()->max('updated_at');

        if ($lineItemMax === null) {
            return $this->updated_at;
        }

        $lineItemDate = Carbon::parse($lineItemMax);

        return $this->updated_at->greaterThan($lineItemDate)
            ? $this->updated_at
            : $lineItemDate;
    }

    public function hasChangedSinceCustomerViewed(): bool
    {
        if ($this->quote_customer_viewed_at === null) {
            return true;
        }

        return $this->latestContentChange()->greaterThan($this->quote_customer_viewed_at);
    }
}
