<?php

use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\User;

test('guests cannot create a customer', function () {
    $this->post(route('customers.store'), [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ])->assertRedirect(route('login'));
});

test('members can create a customer', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($user)
        ->post(route('customers.store'), [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'company' => 'Analytical Engines',
            'email' => 'ada@example.com',
            'phone' => '555-0100',
            'notes' => 'A brilliant mathematician.',
        ])
        ->assertRedirect();

    $customer = Customer::query()->where('email', 'ada@example.com')->first();
    expect($customer)->not->toBeNull();
    expect($customer->first_name)->toBe('Ada');
    expect($customer->last_name)->toBe('Lovelace');
    expect($customer->account_id)->toBe($account->id);
});

test('account_id from request payload is ignored and context wins', function () {
    $account = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($user)
        ->post(route('customers.store'), [
            'account_id' => $otherAccount->id,
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
        ])
        ->assertRedirect();

    $customer = Customer::query()->where('first_name', 'Grace')->first();
    expect($customer->account_id)->toBe($account->id);
});

test('first_name and last_name are required', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($user)
        ->post(route('customers.store'), [
            'first_name' => '',
            'last_name' => '',
        ])
        ->assertSessionHasErrors(['first_name', 'last_name']);
});

test('email must be valid', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($user)
        ->post(route('customers.store'), [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'not-an-email',
        ])
        ->assertSessionHasErrors(['email']);
});

test('notes are capped at 5000 characters', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($user)
        ->post(route('customers.store'), [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'notes' => str_repeat('x', 5001),
        ])
        ->assertSessionHasErrors(['notes']);
});

test('blank email is stored as null', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($user)
        ->post(route('customers.store'), [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => '',
        ])
        ->assertRedirect();

    $customer = Customer::query()->where('first_name', 'Ada')->first();
    expect($customer->email)->toBeNull();
});

test('activity log records customer creation', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($user)
        ->post(route('customers.store'), [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
        ]);

    $log = ActivityLog::query()
        ->where('event', 'customer.created')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->metadata['customer_name'] ?? null)->toBe('Ada Lovelace');
    expect($log->account_id)->toBe($account->id);
    expect($log->user_id)->toBe($user->id);
});

test('users without an account get 403', function () {
    $sysop = User::factory()->sysop()->create();

    $this->actingAs($sysop)
        ->post(route('customers.store'), [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
        ])
        ->assertForbidden();
});
