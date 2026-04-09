<?php

use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\User;

test('guests are redirected to login', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->put(route('sysops.accounts.users.activation.update', [$account, $user]))
        ->assertRedirect(route('login'));
});

test('non-sysop users get 403', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($user)
        ->put(route('sysops.accounts.users.activation.update', [$account, $user]), [
            'deactivated' => true,
        ])
        ->assertForbidden();
});

test('sysop can deactivate a user', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($sysop)
        ->put(route('sysops.accounts.users.activation.update', [$account, $user]), [
            'deactivated' => true,
        ])
        ->assertRedirect();

    expect($user->fresh()->deactivated_at)->not->toBeNull();
});

test('sysop can activate a deactivated user', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();
    $user = User::factory()->deactivated()->create(['account_id' => $account->id]);

    $this->actingAs($sysop)
        ->put(route('sysops.accounts.users.activation.update', [$account, $user]), [
            'deactivated' => false,
        ])
        ->assertRedirect();

    expect($user->fresh()->deactivated_at)->toBeNull();
});

test('cannot deactivate user from different account', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    $otherUser = $otherAccount->owner;

    $this->actingAs($sysop)
        ->put(route('sysops.accounts.users.activation.update', [$account, $otherUser]), [
            'deactivated' => true,
        ])
        ->assertNotFound();
});

test('cannot deactivate a sysop user', function () {
    $sysop = User::factory()->sysop()->create();
    $targetSysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();

    $this->actingAs($sysop)
        ->put(route('sysops.accounts.users.activation.update', [$account, $targetSysop]), [
            'deactivated' => true,
        ])
        ->assertNotFound();
});

test('cannot deactivate yourself', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $user->forceFill(['is_sysop' => true])->save();

    $this->actingAs($user)
        ->put(route('sysops.accounts.users.activation.update', [$account, $user]), [
            'deactivated' => true,
        ])
        ->assertStatus(422);
});

test('activity log is created on deactivation', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($sysop)
        ->put(route('sysops.accounts.users.activation.update', [$account, $user]), [
            'deactivated' => true,
        ]);

    expect(ActivityLog::where('description', 'User deactivated')->exists())->toBeTrue();
});

test('activity log is created on activation', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();
    $user = User::factory()->deactivated()->create(['account_id' => $account->id]);

    $this->actingAs($sysop)
        ->put(route('sysops.accounts.users.activation.update', [$account, $user]), [
            'deactivated' => false,
        ]);

    expect(ActivityLog::where('description', 'User activated')->exists())->toBeTrue();
});

test('no activity log when state unchanged', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();
    $user = User::factory()->deactivated()->create(['account_id' => $account->id]);

    $this->actingAs($sysop)
        ->put(route('sysops.accounts.users.activation.update', [$account, $user]), [
            'deactivated' => true,
        ])
        ->assertRedirect();

    expect(ActivityLog::count())->toBe(0);
});

test('validation rejects non-boolean deactivated field', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($sysop)
        ->put(route('sysops.accounts.users.activation.update', [$account, $user]), [
            'deactivated' => 'not-a-boolean',
        ])
        ->assertSessionHasErrors('deactivated');
});

test('validation rejects missing deactivated field', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($sysop)
        ->put(route('sysops.accounts.users.activation.update', [$account, $user]), [])
        ->assertSessionHasErrors('deactivated');
});
