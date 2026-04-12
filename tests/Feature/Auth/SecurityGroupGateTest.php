<?php

use App\Contexts\AccountContext;
use App\Enums\SecurityGroup;
use App\Models\Account;
use App\Models\SecurityGroupUser;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('admin users pass any gate check', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    app(AccountContext::class)->set($account);

    SecurityGroupUser::create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $user->load('securityGroupMemberships');

    expect(Gate::forUser($user)->allows('some-arbitrary-ability'))->toBeTrue();
});

test('non-admin users are not automatically authorized', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    app(AccountContext::class)->set($account);

    $user->load('securityGroupMemberships');

    expect(Gate::forUser($user)->allows('some-arbitrary-ability'))->toBeFalse();
});

test('sysop users pass any gate check', function () {
    $user = User::factory()->sysop()->create();

    expect(Gate::forUser($user)->allows('some-arbitrary-ability'))->toBeTrue();
});
