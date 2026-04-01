<?php

use App\Models\Account;
use App\Models\User;

beforeEach(function () {
    $this->markTestSkipped('Registration is currently disabled.');
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('new user registration creates an account', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'test@example.com')->first();

    expect($user->account_id)->not->toBeNull()
        ->and($user->account->owner_user_id)->toBe($user->id)
        ->and($user->account->name)->toBe("Test User's Account")
        ->and(Account::count())->toBe(1);
});
