<?php

use App\Enums\SecurityGroup;
use App\Models\Account;
use App\Models\SecurityGroupUser;
use App\Models\User;

test('guests are redirected to login', function () {
    $this->get(route('admin.users.index'))
        ->assertRedirect(route('login'));
});

test('non-admin users get 403', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($user)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

test('admin can view the users list', function () {
    $account = Account::factory()->create();
    $admin = $account->owner;

    SecurityGroupUser::create([
        'account_id' => $account->id,
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/index')
            ->has('users')
            ->has('securityGroups')
        );
});

test('users list is scoped to the current account', function () {
    $account = Account::factory()->create();
    $admin = $account->owner;
    $accountUser = User::factory()->forAccount($account)->create();

    $otherAccount = Account::factory()->create();

    SecurityGroupUser::create([
        'account_id' => $account->id,
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn ($page) => $page
            ->has('users', 2)
        );
});

test('security group data is included', function () {
    $account = Account::factory()->create();
    $admin = $account->owner;

    SecurityGroupUser::create([
        'account_id' => $account->id,
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn ($page) => $page
            ->where('securityGroups.0.value', 'admin')
        );
});

test('users without an account get 403', function () {
    $sysop = User::factory()->sysop()->create();

    $this->actingAs($sysop)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});
