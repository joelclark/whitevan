<?php

use App\Enums\SecurityGroup;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\SecurityGroupUser;
use App\Models\User;

test('guests are redirected to login', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->put(route('admin.users.security-groups.update', $user))
        ->assertRedirect(route('login'));
});

test('non-admin users get 403', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($user)
        ->put(route('admin.users.security-groups.update', $user), [
            'security_groups' => ['admin'],
        ])
        ->assertForbidden();
});

test('admin can add a security group to a user', function () {
    $account = Account::factory()->create();
    $admin = $account->owner;
    $user = User::factory()->create(['account_id' => $account->id]);

    SecurityGroupUser::create([
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.security-groups.update', $user), [
            'security_groups' => ['admin'],
        ])
        ->assertRedirect();

    expect(SecurityGroupUser::where('user_id', $user->id)->where('security_group', 'admin')->exists())->toBeTrue();
});

test('admin can remove a security group from another user', function () {
    $account = Account::factory()->create();
    $admin = $account->owner;
    $user = User::factory()->create(['account_id' => $account->id]);

    SecurityGroupUser::create([
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    SecurityGroupUser::create([
        'user_id' => $user->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.security-groups.update', $user), [
            'security_groups' => [],
        ])
        ->assertRedirect();

    expect(SecurityGroupUser::where('user_id', $user->id)->exists())->toBeFalse();
});

test('admin cannot remove their own admin group', function () {
    $account = Account::factory()->create();
    $admin = $account->owner;

    SecurityGroupUser::create([
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.security-groups.update', $admin), [
            'security_groups' => [],
        ])
        ->assertStatus(422);

    expect(SecurityGroupUser::where('user_id', $admin->id)->where('security_group', 'admin')->exists())->toBeTrue();
});

test('cannot modify security groups for a sysop user', function () {
    $account = Account::factory()->create();
    $admin = $account->owner;
    $sysop = User::factory()->sysop()->create();

    SecurityGroupUser::create([
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    // Sysops have account_id = null, so the account scope check 404s first.
    // The explicit isSysop() guard is defense-in-depth for if that invariant changes.
    $this->actingAs($admin)
        ->put(route('admin.users.security-groups.update', $sysop), [
            'security_groups' => ['admin'],
        ])
        ->assertNotFound();
});

test('cannot modify users from a different account', function () {
    $account = Account::factory()->create();
    $admin = $account->owner;
    $otherAccount = Account::factory()->create();
    $otherUser = $otherAccount->owner;

    SecurityGroupUser::create([
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.security-groups.update', $otherUser), [
            'security_groups' => ['admin'],
        ])
        ->assertNotFound();
});

test('activity log is created when adding a security group', function () {
    $account = Account::factory()->create();
    $admin = $account->owner;
    $user = User::factory()->create(['account_id' => $account->id]);

    SecurityGroupUser::create([
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.security-groups.update', $user), [
            'security_groups' => ['admin'],
        ]);

    expect(ActivityLog::where('description', 'Security group added: admin')->exists())->toBeTrue();
});

test('activity log is created when removing a security group', function () {
    $account = Account::factory()->create();
    $admin = $account->owner;
    $user = User::factory()->create(['account_id' => $account->id]);

    SecurityGroupUser::create([
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    SecurityGroupUser::create([
        'user_id' => $user->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.security-groups.update', $user), [
            'security_groups' => [],
        ]);

    expect(ActivityLog::where('description', 'Security group removed: admin')->exists())->toBeTrue();
});

test('idempotent update makes no changes', function () {
    $account = Account::factory()->create();
    $admin = $account->owner;
    $user = User::factory()->create(['account_id' => $account->id]);

    SecurityGroupUser::create([
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    SecurityGroupUser::create([
        'user_id' => $user->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.security-groups.update', $user), [
            'security_groups' => ['admin'],
        ])
        ->assertRedirect();

    expect(SecurityGroupUser::where('user_id', $user->id)->count())->toBe(1);
    expect(ActivityLog::count())->toBe(0);
});

test('validation rejects invalid security group values', function () {
    $account = Account::factory()->create();
    $admin = $account->owner;
    $user = User::factory()->create(['account_id' => $account->id]);

    SecurityGroupUser::create([
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.security-groups.update', $user), [
            'security_groups' => ['nonexistent'],
        ])
        ->assertSessionHasErrors('security_groups.0');
});

test('validation rejects missing security_groups field', function () {
    $account = Account::factory()->create();
    $admin = $account->owner;
    $user = User::factory()->create(['account_id' => $account->id]);

    SecurityGroupUser::create([
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.security-groups.update', $user), [])
        ->assertSessionHasErrors('security_groups');
});
