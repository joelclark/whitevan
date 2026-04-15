<?php

use App\Models\Account;
use App\Models\User;

test('any authenticated account member can access customers', function () {
    $account = Account::factory()->create();
    $member = User::factory()->forAccount($account)->create();

    $this->actingAs($member)
        ->get(route('customers.index'))
        ->assertOk();
});

test('guests are redirected to login', function () {
    $this->get(route('customers.index'))
        ->assertRedirect(route('login'));
});
