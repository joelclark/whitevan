<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Contexts\AccountContext;
use App\Enums\SecurityGroup;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the accounts this user belongs to.
     */
    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class)->using(AccountUser::class);
    }

    /**
     * Get the account owned by this user.
     */
    public function ownedAccount(): HasOne
    {
        return $this->hasOne(Account::class, 'owner_user_id');
    }

    public function defaultAccount(): ?Account
    {
        return $this->accounts()->orderBy('account_user.id')->first();
    }

    public function currentAccount(): ?Account
    {
        return app(AccountContext::class)->resolveForUser($this);
    }

    public function isMemberOf(Account $account): bool
    {
        return $this->accounts()->whereKey($account->getKey())->exists();
    }

    /**
     * Get the security group memberships for this user.
     */
    public function securityGroupMemberships(): HasMany
    {
        return $this->hasMany(SecurityGroupUser::class);
    }

    /**
     * @return Collection<int, SecurityGroup>
     */
    public function securityGroupsForAccount(?int $accountId): Collection
    {
        if ($accountId === null) {
            return collect();
        }

        return $this->securityGroupMemberships
            ->where('account_id', $accountId)
            ->pluck('security_group');
    }

    /**
     * Determine if the user belongs to a security group in the current account.
     */
    public function hasSecurityGroup(SecurityGroup $group): bool
    {
        return $this->securityGroupsForAccount(app(AccountContext::class)->id())
            ->contains($group);
    }

    /**
     * Determine if the user is an admin in the current account.
     */
    public function isAdmin(): bool
    {
        return $this->hasSecurityGroup(SecurityGroup::Admin);
    }

    /**
     * Determine if the user is a sysop.
     */
    public function isSysop(): bool
    {
        return $this->is_sysop === true;
    }

    /**
     * Determine if the user has been deactivated.
     */
    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_sysop' => 'boolean',
            'deactivated_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }
}
