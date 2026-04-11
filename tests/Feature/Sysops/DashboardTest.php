<?php

use App\Models\Account;
use App\Models\User;

test('guests are redirected to login', function () {
    $this->get('/sysops/dashboard')->assertRedirect(route('login'));
});

test('non-sysop users get 403', function () {
    $account = Account::factory()->create();

    $this->actingAs($account->owner)
        ->get('/sysops/dashboard')
        ->assertForbidden();
});

test('sysops see the dashboard with a 7 point active user series', function () {
    $sysop = User::factory()->sysop()->create();

    $this->actingAs($sysop)
        ->get('/sysops/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sysops/dashboard')
            ->has('activeUserSeries', 7)
            ->has('activeUserSeries.0', fn ($point) => $point
                ->has('date')
                ->has('count')
            )
        );
});

test('sysops hitting /dashboard are redirected to /sysops/dashboard', function () {
    $sysop = User::factory()->sysop()->create();

    $this->actingAs($sysop)
        ->get('/dashboard')
        ->assertRedirect('/sysops/dashboard');
});

test('non-sysops still see the regular /dashboard page', function () {
    $account = Account::factory()->create();

    $this->actingAs($account->owner)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('dashboard'));
});
