<?php

use App\Models\Account;
use App\Models\Customer;
use App\Models\Estimate;

test('guests are redirected to login', function () {
    $this->get(route('estimates.index'))
        ->assertRedirect(route('login'));
});

test('members can view the estimates list', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);

    Estimate::factory()->count(3)->forCustomer($customer)->create();

    $this->actingAs($user)
        ->get(route('estimates.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('estimates/index')
            ->has('estimates.data', 3)
        );
});

test('estimates list is scoped to the current account', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    Estimate::factory()->forCustomer($customer)->count(2)->create();

    $otherAccount = Account::factory()->create();
    $otherCustomer = Customer::factory()->create(['account_id' => $otherAccount->id]);
    Estimate::factory()->forCustomer($otherCustomer)->count(5)->create();

    $this->actingAs($user)
        ->get(route('estimates.index'))
        ->assertInertia(fn ($page) => $page->has('estimates.data', 2));
});

test('the search query filters by title', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    // Fixed customer fields so faker randomness never collides with the
    // search term.
    $customer = Customer::factory()->create([
        'account_id' => $account->id,
        'first_name' => 'Jane',
        'last_name' => 'Roe',
        'company' => null,
    ]);

    Estimate::factory()->forCustomer($customer)->create(['title' => 'Zeppelin project']);
    Estimate::factory()->forCustomer($customer)->create(['title' => 'Alpha upgrade']);

    $this->actingAs($user)
        ->get(route('estimates.index', ['search' => 'Zeppelin']))
        ->assertInertia(fn ($page) => $page->has('estimates.data', 1));
});
