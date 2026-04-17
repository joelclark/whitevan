<?php

use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Project;
use Carbon\CarbonImmutable;

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

test('edit payload exposes project + estimate counts for the projects panel', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);

    $older = Project::factory()->forCustomer($customer)->create([
        'name' => 'Older project',
        'updated_at' => CarbonImmutable::now()->subDays(7),
    ]);
    Estimate::factory()->forProject($older)->count(2)->create();

    $newer = Project::factory()->forCustomer($customer)->create([
        'name' => 'Newer project',
        'updated_at' => CarbonImmutable::now()->subMinutes(5),
    ]);
    Estimate::factory()->forProject($newer)->count(3)->create();

    $this->actingAs($user)
        ->get(route('customers.edit', $customer))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('customers/edit')
            ->where('customer.projects_count', 2)
            ->where('customer.estimates_count', 5)
            ->has('customer.projects', 2)
            // Ordered by updated_at desc — the newer project comes first.
            ->where('customer.projects.0.id', $newer->id)
            ->where('customer.projects.0.name', 'Newer project')
            ->where('customer.projects.0.estimates_count', 3)
            ->where('customer.projects.1.id', $older->id)
            ->where('customer.projects.1.estimates_count', 2)
        );
});

test('edit payload returns empty projects collection when none exist', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);

    $this->actingAs($user)
        ->get(route('customers.edit', $customer))
        ->assertInertia(fn ($page) => $page
            ->where('customer.projects_count', 0)
            ->where('customer.estimates_count', 0)
            ->has('customer.projects', 0)
        );
});
