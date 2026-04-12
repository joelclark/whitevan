<?php

use App\Contexts\AccountContext;
use App\Models\Account;
use App\Models\User;

test('account context is a singleton', function () {
    $context1 = app(AccountContext::class);
    $context2 = app(AccountContext::class);

    expect($context1)->toBe($context2);
});

test('account context defaults to null', function () {
    $context = app(AccountContext::class);

    expect($context->get())->toBeNull()
        ->and($context->id())->toBeNull();
});

test('account context can be set and retrieved', function () {
    $account = Account::factory()->create();
    $context = app(AccountContext::class);

    $context->set($account);

    expect($context->get())->toBe($account)
        ->and($context->id())->toBe($account->id);
});

test('middleware sets account context for authenticated user', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($user)->get(route('dashboard'));

    $context = app(AccountContext::class);

    expect($context->id())->toBe($account->id);
});

test('middleware does not set account context for unauthenticated request', function () {
    $this->get('/');

    $context = app(AccountContext::class);

    expect($context->id())->toBeNull();
});

test('middleware does not set account context for user without account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'));

    $context = app(AccountContext::class);

    expect($context->id())->toBeNull();
});

test('account factory links owner to the account', function () {
    $account = Account::factory()->create();

    expect($account->owner->isMemberOf($account))->toBeTrue();
});
