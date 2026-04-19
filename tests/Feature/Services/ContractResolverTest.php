<?php

use App\Models\Account;
use App\Models\AccountContractOverride;
use App\Models\ContractTemplate;
use App\Services\ContractResolver;

beforeEach(function () {
    $this->resolver = new ContractResolver;
    ContractTemplate::factory()->create([
        'kind' => ContractTemplate::DEFAULT_KIND,
        'body' => '# Default body',
    ]);
});

test('returns the system default when no override exists', function () {
    $account = Account::factory()->create();

    expect($this->resolver->bodyForAccount($account))->toBe('# Default body');
    expect($this->resolver->overrideFor($account))->toBeNull();
});

test('returns the override when one exists', function () {
    $account = Account::factory()->create();
    AccountContractOverride::factory()->create([
        'account_id' => $account->id,
        'body' => '# Override body',
    ]);

    expect($this->resolver->bodyForAccount($account))->toBe('# Override body');
    expect($this->resolver->overrideFor($account))->not->toBeNull();
});

test('overrideFor finds the row regardless of active AccountContext', function () {
    // Simulates a sysop looking at another account: context is unset (or set
    // to something else) but the resolver must still see the override.
    $account = Account::factory()->create();
    AccountContractOverride::factory()->create([
        'account_id' => $account->id,
        'body' => 'custom',
    ]);

    expect($this->resolver->bodyForAccount($account))->toBe('custom');
});
