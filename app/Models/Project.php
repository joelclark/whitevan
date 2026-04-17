<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'customer_id',
    'name',
    'site_address_line_1',
    'site_address_line_2',
    'site_city',
    'site_state',
    'site_zip',
    'notes',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use BelongsToAccount, HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        // Cascade (soft) delete to child estimates so each Estimate's own
        // deleting hook fires and cleans up its PDF + floorplan PNGs.
        // Using each->delete() instead of ->delete() on the relation
        // query ensures per-model events run.
        static::deleting(function (self $project): void {
            $project->estimates()->get()->each->delete();
        });
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<Estimate, $this>
     */
    public function estimates(): HasMany
    {
        return $this->hasMany(Estimate::class);
    }
}
