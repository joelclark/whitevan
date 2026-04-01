<?php

use App\Models\Account;
use App\Models\User;
use Database\Seeders\DevSeeder;
use Illuminate\Support\Facades\Hash;

test('dev seeder creates dev user', function () {
    // we don't have to test them all --jc
    $this->seed(DevSeeder::class);

    $user = User::where('email', 'dev@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Dev User')
        ->and(Hash::check('retryfilterqueue', $user->password))->toBeTrue();
});

test('dev seeder creates account for dev user', function () {
    $this->seed(DevSeeder::class);

    $user = User::where('email', 'dev@example.com')->first();

    expect($user->account)->not->toBeNull()
        ->and($user->account->name)->toBe("Dev User's Account")
        ->and($user->account->owner_user_id)->toBe($user->id);
});

test('dev seeder is idempotent', function () {
    $this->seed(DevSeeder::class);
    $this->seed(DevSeeder::class);

    expect(User::where('email', 'dev@example.com')->count())->toBe(1)
        ->and(Account::count())->toBe(1);
});
