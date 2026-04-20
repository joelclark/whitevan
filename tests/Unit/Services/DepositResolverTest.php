<?php

use App\Models\Account;
use App\Models\AccountDepositOverride;
use App\Models\DepositDefaults;
use App\Services\DepositResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->resolver = new DepositResolver;
    $this->account = Account::factory()->create();
});

test('returns defaults when no override exists', function () {
    expect($this->resolver->materialPercentFor($this->account))->toBe(100);
    expect($this->resolver->laborPercentFor($this->account))->toBe(80);
});

test('returns override when one is set for both sides', function () {
    AccountDepositOverride::factory()->create([
        'account_id' => $this->account->id,
        'material_deposit_percent' => 50,
        'labor_deposit_percent' => 25,
    ]);

    expect($this->resolver->materialPercentFor($this->account))->toBe(50);
    expect($this->resolver->laborPercentFor($this->account))->toBe(25);
});

test('partial override falls back to default only on the null side', function () {
    AccountDepositOverride::factory()->create([
        'account_id' => $this->account->id,
        'material_deposit_percent' => null,
        'labor_deposit_percent' => 30,
    ]);

    expect($this->resolver->materialPercentFor($this->account))->toBe(100);
    expect($this->resolver->laborPercentFor($this->account))->toBe(30);
});

test('sysop edits to defaults flow through resolver for all accounts', function () {
    DepositDefaults::default()->update([
        'material_deposit_percent' => 90,
        'labor_deposit_percent' => 70,
    ]);

    expect($this->resolver->materialPercentFor($this->account))->toBe(90);
    expect($this->resolver->laborPercentFor($this->account))->toBe(70);
});
