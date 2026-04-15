<?php

use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Customer;

test('members can update a customer', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create([
        'account_id' => $account->id,
        'first_name' => 'Before',
        'last_name' => 'Name',
    ]);

    $this->actingAs($user)
        ->put(route('customers.update', $customer), [
            'first_name' => 'After',
            'last_name' => 'Name',
            'email' => 'after@example.com',
        ])
        ->assertRedirect();

    $fresh = Customer::query()->find($customer->id);
    expect($fresh->first_name)->toBe('After');
    expect($fresh->email)->toBe('after@example.com');
});

test('cannot update a customer from another account', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $otherAccount = Account::factory()->create();
    $otherCustomer = Customer::factory()->create(['account_id' => $otherAccount->id]);

    $this->actingAs($user)
        ->put(route('customers.update', $otherCustomer), [
            'first_name' => 'Hack',
            'last_name' => 'Attempt',
        ])
        ->assertNotFound();
});

test('email must be unique within the same account', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    Customer::factory()->create([
        'account_id' => $account->id,
        'email' => 'taken@example.com',
    ]);

    $target = Customer::factory()->create([
        'account_id' => $account->id,
        'email' => 'target@example.com',
    ]);

    $this->actingAs($user)
        ->put(route('customers.update', $target), [
            'first_name' => $target->first_name,
            'last_name' => $target->last_name,
            'email' => 'taken@example.com',
        ])
        ->assertSessionHasErrors(['email']);
});

test('same email is allowed on the same record', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $customer = Customer::factory()->create([
        'account_id' => $account->id,
        'email' => 'same@example.com',
    ]);

    $this->actingAs($user)
        ->put(route('customers.update', $customer), [
            'first_name' => 'Updated',
            'last_name' => $customer->last_name,
            'email' => 'same@example.com',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

test('email duplication across accounts is allowed', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $otherAccount = Account::factory()->create();
    Customer::factory()->create([
        'account_id' => $otherAccount->id,
        'email' => 'shared@example.com',
    ]);

    $customer = Customer::factory()->create([
        'account_id' => $account->id,
        'email' => 'local@example.com',
    ]);

    $this->actingAs($user)
        ->put(route('customers.update', $customer), [
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'email' => 'shared@example.com',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

test('soft deleted sibling does not block email reuse', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $deleted = Customer::factory()->create([
        'account_id' => $account->id,
        'email' => 'reuse@example.com',
    ]);
    $deleted->delete();

    $active = Customer::factory()->create([
        'account_id' => $account->id,
        'email' => 'other@example.com',
    ]);

    $this->actingAs($user)
        ->put(route('customers.update', $active), [
            'first_name' => $active->first_name,
            'last_name' => $active->last_name,
            'email' => 'reuse@example.com',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

test('blank email clears the value', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $customer = Customer::factory()->create([
        'account_id' => $account->id,
        'email' => 'initial@example.com',
    ]);

    $this->actingAs($user)
        ->put(route('customers.update', $customer), [
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'email' => '',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $fresh = Customer::query()->find($customer->id);
    expect($fresh->email)->toBeNull();
});

test('activity log records customer update', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);

    $this->actingAs($user)
        ->put(route('customers.update', $customer), [
            'first_name' => 'Updated',
            'last_name' => 'Name',
        ]);

    $log = ActivityLog::query()
        ->where('event', 'customer.updated')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->metadata['customer_id'] ?? null)->toBe($customer->id);
});
