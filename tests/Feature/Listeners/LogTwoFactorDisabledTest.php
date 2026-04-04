<?php

use App\Listeners\LogTwoFactorDisabled;
use App\Models\User;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

test('disabling two-factor authentication records an activity log', function () {
    $user = User::factory()->create();

    $listener = new LogTwoFactorDisabled;
    $listener->handle(new TwoFactorAuthenticationDisabled($user));

    $this->assertDatabaseHas('activity_logs', [
        'type' => 'info',
        'description' => 'Two-factor authentication disabled',
        'user_id' => $user->id,
    ]);
});
