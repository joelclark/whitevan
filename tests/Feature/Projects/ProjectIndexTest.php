<?php

use App\Models\Account;
use App\Models\Customer;
use App\Models\Project;

test('guests are redirected to login', function () {
    $this->get(route('projects.index'))
        ->assertRedirect(route('login'));
});

test('members can view the projects list', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);

    Project::factory()->forCustomer($customer)->count(3)->create();

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/index')
            ->has('projects.data', 3)
        );
});

test('projects list is scoped to the current account', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    Project::factory()->forCustomer($customer)->count(2)->create();

    $otherAccount = Account::factory()->create();
    $otherCustomer = Customer::factory()->create(['account_id' => $otherAccount->id]);
    Project::factory()->forCustomer($otherCustomer)->count(5)->create();

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertInertia(fn ($page) => $page->has('projects.data', 2));
});

test('projects are ordered by last_activity_at DESC', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);

    $older = Project::factory()->forCustomer($customer)->create(['name' => 'Older']);
    $older->forceFill(['last_activity_at' => now()->subDays(3)])->save();

    $newer = Project::factory()->forCustomer($customer)->create(['name' => 'Newer']);
    $newer->forceFill(['last_activity_at' => now()])->save();

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertInertia(fn ($page) => $page
            ->where('projects.data.0.name', 'Newer')
            ->where('projects.data.1.name', 'Older')
        );
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

    Project::factory()->forCustomer($customer)->create(['name' => 'Zeppelin hangar']);
    Project::factory()->forCustomer($customer)->create(['name' => 'Boring office']);

    $this->actingAs($user)
        ->get(route('projects.index', ['search' => 'Zeppelin']))
        ->assertInertia(fn ($page) => $page->has('projects.data', 1));
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
    $acme = Customer::factory()->create([
        'account_id' => $account->id,
        'first_name' => 'Bob',
        'last_name' => 'Smith',
        'company' => 'Acme Widgets',
    ]);
    $other = Customer::factory()->create([
        'account_id' => $account->id,
        'first_name' => 'Charlie',
        'last_name' => 'Jones',
        'company' => null,
    ]);

    Project::factory()->forCustomer($ada)->create(['name' => 'one']);
    Project::factory()->forCustomer($acme)->create(['name' => 'two']);
    Project::factory()->forCustomer($other)->create(['name' => 'three']);

    $this->actingAs($user)
        ->get(route('projects.index', ['search' => 'Lovelace']))
        ->assertInertia(fn ($page) => $page->has('projects.data', 1));

    $this->actingAs($user)
        ->get(route('projects.index', ['search' => 'Acme']))
        ->assertInertia(fn ($page) => $page->has('projects.data', 1));
});

test('list payload includes customer for each row', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create([
        'account_id' => $account->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'company' => 'Analytical Engines',
    ]);
    Project::factory()->forCustomer($customer)->create(['name' => 'Engine No. 2']);

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertInertia(fn ($page) => $page
            ->component('projects/index')
            ->where('projects.data.0.name', 'Engine No. 2')
            ->where('projects.data.0.customer.id', $customer->id)
            ->where('projects.data.0.customer.first_name', 'Ada')
            ->where('projects.data.0.customer.company', 'Analytical Engines')
        );
});
