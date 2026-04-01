<?php

namespace App\Concerns;

use App\Contexts\AccountContext;
use App\Models\Account;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToAccount
{
    /**
     * Boot the trait: add a global scope that filters by the current account context,
     * and auto-set account_id on new records.
     */
    public static function bootBelongsToAccount(): void
    {
        static::addGlobalScope('account', function (Builder $builder): void {
            $context = app(AccountContext::class);

            if ($context->id() !== null) {
                $builder->where($builder->getModel()->getTable().'.account_id', $context->id());
            }
        });

        static::creating(function (Model $model): void {
            $context = app(AccountContext::class);

            if ($model->account_id === null && $context->id() !== null) {
                $model->account_id = $context->id();
            }
        });
    }

    /**
     * Get the account that this model belongs to.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
