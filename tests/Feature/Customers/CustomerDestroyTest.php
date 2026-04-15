<?php

use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Customer;

test('members can delete a customer', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);

    $this->actingAs($user)
        ->delete(route('customers.destroy', $customer))
        ->assertRedirect(route('customers.index'));

    $fresh = Customer::withoutGlobalScopes()->find($customer->id);
    expect($fresh)->not->toBeNull();
    expect($fresh->deleted_at)->not->toBeNull();
});

test('deleted customers are hidden from the index', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);

    $this->actingAs($user)->delete(route('customers.destroy', $customer));

    $this->actingAs($user)
        ->get(route('customers.index'))
        ->assertInertia(fn ($page) => $page->has('customers.data', 0));
});

test('deleted customers 404 on edit', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);

    $this->actingAs($user)->delete(route('customers.destroy', $customer));

    $this->actingAs($user)
        ->get(route('customers.edit', $customer->id))
        ->assertNotFound();
});

test('cannot delete a customer from another account', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $otherAccount = Account::factory()->create();
    $otherCustomer = Customer::factory()->create(['account_id' => $otherAccount->id]);

    $this->actingAs($user)
        ->delete(route('customers.destroy', $otherCustomer))
        ->assertNotFound();

    $fresh = Customer::withoutGlobalScopes()->find($otherCustomer->id);
    expect($fresh)->not->toBeNull();
    expect($fresh->deleted_at)->toBeNull();
});

test('activity log records customer deletion', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create([
        'account_id' => $account->id,
        'first_name' => 'Deleted',
        'last_name' => 'Customer',
    ]);

    $this->actingAs($user)->delete(route('customers.destroy', $customer));

    $log = ActivityLog::query()
        ->where('event', 'customer.deleted')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->metadata['customer_id'] ?? null)->toBe($customer->id);
    expect($log->metadata['customer_name'] ?? null)->toBe('Deleted Customer');
});
