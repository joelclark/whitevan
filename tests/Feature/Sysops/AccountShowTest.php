<?php

use App\Models\Account;
use App\Models\User;

test('guests are redirected to login', function () {
    $account = Account::factory()->create();

    $this->get("/sysops/{$account->id}")->assertRedirect(route('login'));
});

test('non-sysop users get 403', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($user)
        ->get("/sysops/{$account->id}")
        ->assertForbidden();
});

test('sysop users can access account show', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();

    $this->actingAs($sysop)
        ->get("/sysops/{$account->id}")
        ->assertOk();
});

test('account show contains account data and users', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();

    $member = User::factory()->forAccount($account)->create();

    $this->actingAs($sysop)
        ->get("/sysops/{$account->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sysops/accounts/show')
            ->where('account.id', $account->id)
            ->where('account.name', $account->name)
            ->has('account.owner')
            ->has('account.users', 2)
        );
});
