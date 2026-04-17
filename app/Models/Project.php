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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * Bump the denormalized activity timestamp used to sort the /projects
     * workspace list. `forceFill` keeps the column out of mass-assignment —
     * mirrors `deactivated_at` / `last_login_at`.
     */
    public function recordActivity(): void
    {
        $this->forceFill(['last_activity_at' => now()])->save();
    }

    protected static function booted(): void
    {
        // Populate `last_activity_at` in-memory at insert time. The DB has
        // `DEFAULT CURRENT_TIMESTAMP` as a safety net, but Eloquent doesn't
        // read back server-side defaults, so a freshly created Project would
        // otherwise carry a null attribute until a manual ->refresh().
        static::creating(function (self $project): void {
            if ($project->last_activity_at === null) {
                $project->last_activity_at = now();
            }
        });

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
