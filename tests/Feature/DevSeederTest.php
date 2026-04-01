<?php

use App\Models\User;
use Database\Seeders\DevSeeder;
use Illuminate\Support\Facades\Hash;

test('dev seeder creates dev user', function () {
    # we don't have to test them all --jc
    $this->seed(DevSeeder::class);

    $user = User::where('email', 'dev@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Dev User')
        ->and(Hash::check('retryfilterqueue', $user->password))->toBeTrue();
});
