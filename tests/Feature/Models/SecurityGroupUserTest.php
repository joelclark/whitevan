<?php

use App\Contexts\AccountContext;
use App\Enums\SecurityGroup;
use App\Models\Account;
use App\Models\SecurityGroupUser;

test('hasSecurityGroup returns true when user has the group', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    app(AccountContext::class)->set($account);

    SecurityGroupUser::create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $user->load('securityGroupMemberships');

    expect($user->hasSecurityGroup(SecurityGroup::Admin))->toBeTrue();
});

test('hasSecurityGroup returns false when user does not have the group', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    app(AccountContext::class)->set($account);

    $user->load('securityGroupMemberships');

    expect($user->hasSecurityGroup(SecurityGroup::Admin))->toBeFalse();
});

test('isAdmin returns true for admin users', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    app(AccountContext::class)->set($account);

    SecurityGroupUser::create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $user->load('securityGroupMemberships');

    expect($user->isAdmin())->toBeTrue();
});

test('isAdmin returns false for non-admin users', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    app(AccountContext::class)->set($account);

    $user->load('securityGroupMemberships');

    expect($user->isAdmin())->toBeFalse();
});

test('security group membership casts security_group to enum', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $membership = SecurityGroupUser::create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    expect($membership->security_group)->toBe(SecurityGroup::Admin);
});
