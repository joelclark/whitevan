<?php

namespace App\Models;

use App\Enums\SecurityGroup;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot model linking users to security groups.
 *
 * This table intentionally has no account_id column — security groups are
 * implicitly scoped to accounts through User.account_id. The
 * SecurityGroupController validates account membership before any mutation.
 */
#[Fillable(['user_id', 'security_group'])]
class SecurityGroupUser extends Model
{
    protected $table = 'security_group_user';

    const UPDATED_AT = null;

    /**
     * Get the user this membership belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'security_group' => SecurityGroup::class,
        ];
    }
}
