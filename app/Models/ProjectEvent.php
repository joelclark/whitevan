<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\ActivityEvent;
use App\Enums\ActorType;
use Database\Factories\ProjectEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'account_id',
    'project_id',
    'estimate_id',
    'event',
    'user_id',
    'actor_type',
    'customer_visible',
    'metadata',
])]
class ProjectEvent extends Model
{
    /** @use HasFactory<ProjectEventFactory> */
    use BelongsToAccount, HasFactory;

    const UPDATED_AT = null;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'event' => ActivityEvent::class,
            'actor_type' => ActorType::class,
            'customer_visible' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Estimate, $this>
     */
    public function estimate(): BelongsTo
    {
        // Timeline entries must survive estimate soft-deletes — an
        // `EstimateDeleted` row is written before the estimate is trashed,
        // and the history view still needs the title/filename for it.
        return $this->belongsTo(Estimate::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
