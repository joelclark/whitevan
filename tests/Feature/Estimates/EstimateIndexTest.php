<?php

use App\Models\Account;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Project;

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

test('the search query filters by project name', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create([
        'account_id' => $account->id,
        'first_name' => 'Jane',
        'last_name' => 'Roe',
        'company' => null,
    ]);

    $matching = Project::factory()->forCustomer($customer)->create(['name' => 'Zeppelin hangar']);
    $other = Project::factory()->forCustomer($customer)->create(['name' => 'Boring office']);

    Estimate::factory()->forProject($matching)->create(['title' => 'irrelevant one']);
    Estimate::factory()->forProject($other)->create(['title' => 'irrelevant two']);

    $this->actingAs($user)
        ->get(route('estimates.index', ['search' => 'Zeppelin']))
        ->assertInertia(fn ($page) => $page->has('estimates.data', 1));
});

test('the search query filters by customer name and company', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $ada = Customer::factory()->create([
        'account_id' => $account->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'company' => null,
    ]);
    $bob = Customer::factory()->create([
        'account_id' => $account->id,
        'first_name' => 'Bob',
        'last_name' => 'Smith',
        'company' => 'Acme Widgets',
    ]);
    $charlie = Customer::factory()->create([
        'account_id' => $account->id,
        'first_name' => 'Charlie',
        'last_name' => 'Jones',
        'company' => null,
    ]);

    Estimate::factory()->forCustomer($ada)->create(['title' => 'irrelevant']);
    Estimate::factory()->forCustomer($bob)->create(['title' => 'irrelevant']);
    Estimate::factory()->forCustomer($charlie)->create(['title' => 'irrelevant']);

    $this->actingAs($user)
        ->get(route('estimates.index', ['search' => 'Lovelace']))
        ->assertInertia(fn ($page) => $page->has('estimates.data', 1));

    $this->actingAs($user)
        ->get(route('estimates.index', ['search' => 'Acme']))
        ->assertInertia(fn ($page) => $page->has('estimates.data', 1));
});

test('list payload includes project with customer for each row', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create([
        'account_id' => $account->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'company' => 'Analytical Engines',
    ]);
    $project = Project::factory()->forCustomer($customer)->create(['name' => 'Engine No. 2']);
    Estimate::factory()->forProject($project)->create(['title' => 'Initial estimate']);

    $this->actingAs($user)
        ->get(route('estimates.index'))
        ->assertInertia(fn ($page) => $page
            ->component('estimates/index')
            ->where('estimates.data.0.project.id', $project->id)
            ->where('estimates.data.0.project.name', 'Engine No. 2')
            ->where('estimates.data.0.project.customer.id', $customer->id)
            ->where('estimates.data.0.project.customer.first_name', 'Ada')
            ->where('estimates.data.0.project.customer.company', 'Analytical Engines')
        );
});
