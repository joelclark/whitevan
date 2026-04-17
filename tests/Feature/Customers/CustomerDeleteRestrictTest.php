<?php

use App\Models\Account;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Project;

test('a customer with projects cannot be deleted', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    Estimate::factory()->forCustomer($customer)->create();

    $this->actingAs($user)
        ->delete(route('customers.destroy', $customer))
        ->assertRedirect()
        ->assertSessionHas('status', 'customer-has-projects');

    expect($customer->refresh()->deleted_at)->toBeNull();
});

test('a customer with an empty project still blocks deletion', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    Project::factory()->forCustomer($customer)->create();

    $this->actingAs($user)
        ->delete(route('customers.destroy', $customer))
        ->assertRedirect()
        ->assertSessionHas('status', 'customer-has-projects');

    expect($customer->refresh()->deleted_at)->toBeNull();
});

test('a customer without projects can still be deleted', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);

    $this->actingAs($user)
        ->delete(route('customers.destroy', $customer))
        ->assertRedirect(route('customers.index'))
        ->assertSessionHas('status', 'customer-deleted');

    expect($customer->refresh()->deleted_at)->not->toBeNull();
});
