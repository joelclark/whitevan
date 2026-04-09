<?php

use App\Models\User;

test('deactivated user cannot log in', function () {
    $user = User::factory()->deactivated()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});

test('deactivated user sees deactivation message', function () {
    $user = User::factory()->deactivated()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHas('errors', function ($errors) {
        return str_contains($errors->first('email'), 'deactivated');
    });
});

test('active user can log in normally', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('deactivated user with active session is logged out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    // Deactivate the user mid-session
    $user->forceFill(['deactivated_at' => now()])->save();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
