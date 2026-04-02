<?php

use App\Models\Account;
use App\Models\User;

test('guests are redirected to login', function () {
    $this->get('/sysops')->assertRedirect(route('login'));
});

test('non-sysop users get 403', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($user)
        ->get('/sysops')
        ->assertForbidden();
});

test('sysop users can access accounts list', function () {
    $user = User::factory()->sysop()->create();

    $this->actingAs($user)
        ->get('/sysops')
        ->assertOk();
});

test('accounts list contains accounts', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();

    $this->actingAs($sysop)
        ->get('/sysops')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sysops/accounts/index')
            ->has('accounts.data', 1)
            ->where('accounts.data.0.id', $account->id)
            ->where('accounts.data.0.name', $account->name)
            ->has('accounts.data.0.owner')
        );
});
