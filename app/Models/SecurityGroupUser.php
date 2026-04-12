<?php

namespace App\Models;

use App\Enums\SecurityGroup;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot model linking users to security groups within an account.
 *
 * Security groups are scoped per-account via the account_id column.
 * A user can be Admin in one account but not another.
 */
#[Fillable(['account_id', 'user_id', 'security_group'])]
class SecurityGroupUser extends Model
{
    protected $table = 'security_group_user';

    const UPDATED_AT = null;

    /**
     * Get the account this membership belongs to.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

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
