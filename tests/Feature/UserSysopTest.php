<?php

use App\Models\User;

test('isSysop returns true for sysop users', function () {
    $user = User::factory()->sysop()->make();

    expect($user->isSysop())->toBeTrue();
});

test('isSysop returns false for normal users', function () {
    $user = User::factory()->make();

    expect($user->isSysop())->toBeFalse();
});

test('is_sysop is not mass assignable', function () {
    $user = User::factory()->create();
    $user->fill(['is_sysop' => true]);

    expect($user->isSysop())->toBeFalse();
});
