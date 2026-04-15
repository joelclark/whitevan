<?php

use App\Models\Account;
use App\Models\Customer;
use App\Models\Estimate;

test('a customer with estimates cannot be deleted', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    Estimate::factory()->forCustomer($customer)->create();

    $this->actingAs($user)
        ->delete(route('customers.destroy', $customer))
        ->assertRedirect()
        ->assertSessionHas('status', 'customer-has-estimates');

    expect($customer->refresh()->deleted_at)->toBeNull();
});

test('a customer without estimates can still be deleted', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);

    $this->actingAs($user)
        ->delete(route('customers.destroy', $customer))
        ->assertRedirect(route('customers.index'))
        ->assertSessionHas('status', 'customer-deleted');

    expect($customer->refresh()->deleted_at)->not->toBeNull();
});
