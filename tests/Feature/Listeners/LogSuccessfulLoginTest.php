<?php

use App\Enums\ActivityEvent;
use App\Listeners\LogSuccessfulLogin;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Login;

test('successful login records an activity log with the typed event', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();

    $log = ActivityLog::where('event', ActivityEvent::UserLoggedIn->value)->first();

    expect($log)->not->toBeNull()
        ->and($log->event)->toBe(ActivityEvent::UserLoggedIn)
        ->and($log->description)->toBe('User logged in')
        ->and($log->metadata)->toMatchArray(['email' => $user->email]);
});

test('successful login updates last_login_at on the user', function () {
    $user = User::factory()->create(['last_login_at' => null]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

test('login event within debounce window does not rewrite last_login_at', function () {
    $user = User::factory()->create();
    $original = now()->subSeconds(10)->startOfSecond();
    $user->forceFill(['last_login_at' => $original])->save();

    (new LogSuccessfulLogin)->handle(new Login('web', $user, false));

    expect($user->fresh()->last_login_at->equalTo($original))->toBeTrue();
});

test('login event outside debounce window rewrites last_login_at', function () {
    $user = User::factory()->create();
    $original = now()->subMinutes(5)->startOfSecond();
    $user->forceFill(['last_login_at' => $original])->save();

    (new LogSuccessfulLogin)->handle(new Login('web', $user, false));

    expect($user->fresh()->last_login_at->equalTo($original))->toBeFalse();
});
