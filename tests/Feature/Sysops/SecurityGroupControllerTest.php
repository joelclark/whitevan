<?php

use App\Enums\SecurityGroup;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\SecurityGroupUser;
use App\Models\User;

test('guests are redirected to login', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->put(route('sysops.accounts.users.security-groups.update', [$account, $user]))
        ->assertRedirect(route('login'));
});

test('non-sysop users get 403', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($user)
        ->put(route('sysops.accounts.users.security-groups.update', [$account, $user]), [
            'security_groups' => ['admin'],
        ])
        ->assertForbidden();
});

test('sysop can add a security group to a user', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($sysop)
        ->put(route('sysops.accounts.users.security-groups.update', [$account, $user]), [
            'security_groups' => ['admin'],
        ])
        ->assertRedirect();

    expect(SecurityGroupUser::where('user_id', $user->id)->where('security_group', 'admin')->exists())->toBeTrue();
});

test('sysop can remove a security group from a user', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();
    $user = $account->owner;

    SecurityGroupUser::create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($sysop)
        ->put(route('sysops.accounts.users.security-groups.update', [$account, $user]), [
            'security_groups' => [],
        ])
        ->assertRedirect();

    expect(SecurityGroupUser::where('user_id', $user->id)->exists())->toBeFalse();
});

test('validation rejects invalid security group values', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($sysop)
        ->put(route('sysops.accounts.users.security-groups.update', [$account, $user]), [
            'security_groups' => ['nonexistent'],
        ])
        ->assertSessionHasErrors('security_groups.0');
});

test('cannot assign groups to a user from a different account', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    $otherUser = $otherAccount->owner;

    $this->actingAs($sysop)
        ->put(route('sysops.accounts.users.security-groups.update', [$account, $otherUser]), [
            'security_groups' => ['admin'],
        ])
        ->assertNotFound();
});

test('activity log is created when adding a security group', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($sysop)
        ->put(route('sysops.accounts.users.security-groups.update', [$account, $user]), [
            'security_groups' => ['admin'],
        ]);

    expect(ActivityLog::where('event', 'user.security_group_added')
        ->where('description', 'Security group added: admin')
        ->exists())->toBeTrue();
});

test('activity log is created when removing a security group', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();
    $user = $account->owner;

    SecurityGroupUser::create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($sysop)
        ->put(route('sysops.accounts.users.security-groups.update', [$account, $user]), [
            'security_groups' => [],
        ]);

    expect(ActivityLog::where('event', 'user.security_group_removed')
        ->where('description', 'Security group removed: admin')
        ->exists())->toBeTrue();
});

test('admin users without sysop status get 403', function () {
    $account = Account::factory()->create();
    $admin = $account->owner;

    SecurityGroupUser::create([
        'account_id' => $account->id,
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($admin)
        ->put(route('sysops.accounts.users.security-groups.update', [$account, $admin]), [
            'security_groups' => ['admin'],
        ])
        ->assertForbidden();
});

test('sysop cannot assign groups to another sysop', function () {
    $sysop = User::factory()->sysop()->create();
    $targetSysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();

    // Sysops have no account memberships, so they 404 on the account mismatch check
    $this->actingAs($sysop)
        ->put(route('sysops.accounts.users.security-groups.update', [$account, $targetSysop]), [
            'security_groups' => ['admin'],
        ])
        ->assertNotFound();
});

test('idempotent update makes no changes', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();
    $user = $account->owner;

    SecurityGroupUser::create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($sysop)
        ->put(route('sysops.accounts.users.security-groups.update', [$account, $user]), [
            'security_groups' => ['admin'],
        ])
        ->assertRedirect();

    expect(SecurityGroupUser::where('user_id', $user->id)->count())->toBe(1);
    expect(ActivityLog::count())->toBe(0);
});

test('validation rejects missing security_groups field', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($sysop)
        ->put(route('sysops.accounts.users.security-groups.update', [$account, $user]), [])
        ->assertSessionHasErrors('security_groups');
});

test('validation rejects non-array security_groups field', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($sysop)
        ->put(route('sysops.accounts.users.security-groups.update', [$account, $user]), [
            'security_groups' => 'admin',
        ])
        ->assertSessionHasErrors('security_groups');
});

test('account show page includes security group data', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();

    $this->actingAs($sysop)
        ->get(route('sysops.accounts.show', $account))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sysops/accounts/show')
            ->has('securityGroups')
            ->where('securityGroups.0.value', 'admin')
        );
});
