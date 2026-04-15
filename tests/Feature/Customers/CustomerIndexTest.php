<?php

use App\Models\Account;
use App\Models\Customer;
use App\Models\User;

test('guests are redirected to login', function () {
    $this->get(route('customers.index'))
        ->assertRedirect(route('login'));
});

test('members can view the customers list', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    Customer::factory()->count(3)->create(['account_id' => $account->id]);

    $this->actingAs($user)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('customers/index')
            ->has('customers.data', 3)
            ->has('customers.current_page')
            ->where('filters.search', '')
        );
});

test('customer list is scoped to the current account', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    Customer::factory()->count(2)->create(['account_id' => $account->id]);

    $otherAccount = Account::factory()->create();
    Customer::factory()->count(5)->create(['account_id' => $otherAccount->id]);

    $this->actingAs($user)
        ->get(route('customers.index'))
        ->assertInertia(fn ($page) => $page->has('customers.data', 2));
});

test('soft deleted customers are hidden from the list', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    Customer::factory()->count(2)->create(['account_id' => $account->id]);
    $deleted = Customer::factory()->create(['account_id' => $account->id]);
    $deleted->delete();

    $this->actingAs($user)
        ->get(route('customers.index'))
        ->assertInertia(fn ($page) => $page->has('customers.data', 2));
});

test('customers are sorted by recently viewed then alphabetically with never viewed last', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $zephyrRecent = Customer::factory()->create([
        'account_id' => $account->id,
        'first_name' => 'Zephyr',
        'last_name' => 'Zulu',
        'last_accessed_at' => now()->subMinutes(1),
    ]);

    $aliceOlder = Customer::factory()->create([
        'account_id' => $account->id,
        'first_name' => 'Alice',
        'last_name' => 'Adams',
        'last_accessed_at' => now()->subHours(1),
    ]);

    $neverBob = Customer::factory()->create([
        'account_id' => $account->id,
        'first_name' => 'Bob',
        'last_name' => 'Brown',
        'last_accessed_at' => null,
    ]);

    $neverAnna = Customer::factory()->create([
        'account_id' => $account->id,
        'first_name' => 'Anna',
        'last_name' => 'Anderson',
        'last_accessed_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('customers.index'))
        ->assertInertia(fn ($page) => $page
            ->where('customers.data.0.id', $zephyrRecent->id)
            ->where('customers.data.1.id', $aliceOlder->id)
            ->where('customers.data.2.id', $neverAnna->id)
            ->where('customers.data.3.id', $neverBob->id)
        );
});

test('search matches substrings across name, company, email, and phone case-insensitively', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $byFirstName = Customer::factory()->create([
        'account_id' => $account->id,
        'first_name' => 'Quinoa',
        'last_name' => 'Smith',
        'company' => null,
        'email' => 'quinoa@example.com',
        'phone' => null,
    ]);

    $byCompany = Customer::factory()->create([
        'account_id' => $account->id,
        'first_name' => 'Pat',
        'last_name' => 'Jones',
        'company' => 'Acme QUInoa Corp',
        'email' => 'pat@example.com',
        'phone' => null,
    ]);

    $byPhone = Customer::factory()->create([
        'account_id' => $account->id,
        'first_name' => 'Sam',
        'last_name' => 'Lee',
        'company' => null,
        'email' => 'sam@example.com',
        'phone' => '555-QUINOA',
    ]);

    Customer::factory()->create([
        'account_id' => $account->id,
        'first_name' => 'Unrelated',
        'last_name' => 'Person',
        'company' => 'Nothing Co',
        'email' => 'unrelated@example.com',
        'phone' => '555-0000',
    ]);

    $response = $this->actingAs($user)
        ->get(route('customers.index', ['search' => 'quinoa']));

    $response->assertInertia(fn ($page) => $page
        ->has('customers.data', 3)
        ->where('filters.search', 'quinoa')
    );
});

test('pagination preserves the search query string', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    Customer::factory()->count(30)->create([
        'account_id' => $account->id,
        'company' => 'Needle',
    ]);

    $this->actingAs($user)
        ->get(route('customers.index', ['search' => 'needle']))
        ->assertInertia(fn ($page) => $page
            ->has('customers.data', 25)
            ->where('customers.total', 30)
            ->where('filters.search', 'needle')
        );
});

test('users without an account get 403', function () {
    $sysop = User::factory()->sysop()->create();

    $this->actingAs($sysop)
        ->get(route('customers.index'))
        ->assertForbidden();
});
