<?php

use App\Enums\SecurityGroup;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\SecurityGroupUser;
use App\Models\User;

test('guests are redirected to login', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->put(route('admin.users.activation.update', $user))
        ->assertRedirect(route('login'));
});

test('non-admin users get 403', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($user)
        ->put(route('admin.users.activation.update', $user), [
            'deactivated' => true,
        ])
        ->assertForbidden();
});

test('admin can deactivate a user', function () {
    $account = Account::factory()->create();
    $admin = $account->owner;
    $user = User::factory()->forAccount($account)->create();

    SecurityGroupUser::create([
        'account_id' => $account->id,
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.activation.update', $user), [
            'deactivated' => true,
        ])
        ->assertRedirect();

    expect($user->fresh()->deactivated_at)->not->toBeNull();
});

test('admin can activate a deactivated user', function () {
    $account = Account::factory()->create();
    $admin = $account->owner;
    $user = User::factory()->deactivated()->forAccount($account)->create();

    SecurityGroupUser::create([
        'account_id' => $account->id,
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.activation.update', $user), [
            'deactivated' => false,
        ])
        ->assertRedirect();

    expect($user->fresh()->deactivated_at)->toBeNull();
});

test('admin cannot deactivate themselves', function () {
    $account = Account::factory()->create();
    $admin = $account->owner;

    SecurityGroupUser::create([
        'account_id' => $account->id,
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.activation.update', $admin), [
            'deactivated' => true,
        ])
        ->assertStatus(422);

    expect($admin->fresh()->deactivated_at)->toBeNull();
});

test('cannot deactivate a sysop user', function () {
    $account = Account::factory()->create();
    $admin = $account->owner;
    $sysop = User::factory()->sysop()->create();

    SecurityGroupUser::create([
        'account_id' => $account->id,
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    // Sysops have no account memberships, so the account scope check 404s first.
    // The explicit isSysop() guard is defense-in-depth for if that invariant changes.
    $this->actingAs($admin)
        ->put(route('admin.users.activation.update', $sysop), [
            'deactivated' => true,
        ])
        ->assertNotFound();
});

test('cannot modify users from a different account', function () {
    $account = Account::factory()->create();
    $admin = $account->owner;
    $otherAccount = Account::factory()->create();
    $otherUser = $otherAccount->owner;

    SecurityGroupUser::create([
        'account_id' => $account->id,
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.activation.update', $otherUser), [
            'deactivated' => true,
        ])
        ->assertNotFound();
});

test('activity log is created on deactivation', function () {
    $account = Account::factory()->create();
    $admin = $account->owner;
    $user = User::factory()->forAccount($account)->create();

    SecurityGroupUser::create([
        'account_id' => $account->id,
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.activation.update', $user), [
            'deactivated' => true,
        ]);

    expect(ActivityLog::where('event', 'user.deactivated')
        ->where('description', 'User deactivated')
        ->exists())->toBeTrue();
});

test('activity log is created on activation', function () {
    $account = Account::factory()->create();
    $admin = $account->owner;
    $user = User::factory()->deactivated()->forAccount($account)->create();

    SecurityGroupUser::create([
        'account_id' => $account->id,
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.activation.update', $user), [
            'deactivated' => false,
        ]);

    expect(ActivityLog::where('event', 'user.activated')
        ->where('description', 'User activated')
        ->exists())->toBeTrue();
});

test('no activity log when state unchanged', function () {
    $account = Account::factory()->create();
    $admin = $account->owner;
    $user = User::factory()->deactivated()->forAccount($account)->create();

    SecurityGroupUser::create([
        'account_id' => $account->id,
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.activation.update', $user), [
            'deactivated' => true,
        ])
        ->assertRedirect();

    expect(ActivityLog::count())->toBe(0);
});

test('validation rejects non-boolean deactivated field', function () {
    $account = Account::factory()->create();
    $admin = $account->owner;
    $user = User::factory()->forAccount($account)->create();

    SecurityGroupUser::create([
        'account_id' => $account->id,
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.activation.update', $user), [
            'deactivated' => 'not-a-boolean',
        ])
        ->assertSessionHasErrors('deactivated');
});

test('validation rejects missing deactivated field', function () {
    $account = Account::factory()->create();
    $admin = $account->owner;
    $user = User::factory()->forAccount($account)->create();

    SecurityGroupUser::create([
        'account_id' => $account->id,
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.activation.update', $user), [])
        ->assertSessionHasErrors('deactivated');
});
