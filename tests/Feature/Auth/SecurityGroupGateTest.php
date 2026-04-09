<?php

use App\Enums\SecurityGroup;
use App\Models\Account;
use App\Models\SecurityGroupUser;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('admin users pass any gate check', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    SecurityGroupUser::create([
        'user_id' => $user->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $user->load('securityGroupMemberships');

    expect(Gate::forUser($user)->allows('some-arbitrary-ability'))->toBeTrue();
});

test('non-admin users are not automatically authorized', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $user->load('securityGroupMemberships');

    expect(Gate::forUser($user)->allows('some-arbitrary-ability'))->toBeFalse();
});

test('sysop users pass any gate check', function () {
    $user = User::factory()->sysop()->create();

    expect(Gate::forUser($user)->allows('some-arbitrary-ability'))->toBeTrue();
});
