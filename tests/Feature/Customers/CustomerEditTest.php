<?php

use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Customer;

test('getting the edit page bumps last_accessed_at without touching updated_at', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $originalUpdatedAt = now()->subDays(3);

    $customer = Customer::factory()->create([
        'account_id' => $account->id,
        'last_accessed_at' => null,
    ]);

    // Force updated_at backwards so we can verify it doesn't get bumped.
    Customer::query()
        ->whereKey($customer->id)
        ->update(['updated_at' => $originalUpdatedAt]);

    $this->actingAs($user)
        ->get(route('customers.edit', $customer))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('customers/edit')
            ->where('customer.id', $customer->id)
        );

    $fresh = Customer::query()->find($customer->id);
    expect($fresh->last_accessed_at)->not->toBeNull();
    expect($fresh->updated_at->timestamp)->toBe($originalUpdatedAt->timestamp);
});

test('viewing a customer does not write to the activity log', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);

    $this->actingAs($user)->get(route('customers.edit', $customer));

    expect(
        ActivityLog::query()
            ->whereIn('event', ['customer.created', 'customer.updated', 'customer.deleted'])
            ->exists()
    )->toBeFalse();
});

test('cannot view a customer from another account', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $otherAccount = Account::factory()->create();
    $otherCustomer = Customer::factory()->create(['account_id' => $otherAccount->id]);

    $this->actingAs($user)
        ->get(route('customers.edit', $otherCustomer))
        ->assertNotFound();
});

test('cannot view a soft deleted customer', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $customer->delete();

    $this->actingAs($user)
        ->get(route('customers.edit', $customer->id))
        ->assertNotFound();
});

test('guests are redirected to login when viewing a customer', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);

    $this->get(route('customers.edit', $customer))
        ->assertRedirect(route('login'));
});
