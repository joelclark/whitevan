<?php

use App\Listeners\LogTwoFactorEnabled;
use App\Models\User;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;

test('enabling two-factor authentication records an activity log', function () {
    $user = User::factory()->create();

    $listener = new LogTwoFactorEnabled;
    $listener->handle(new TwoFactorAuthenticationEnabled($user));

    $this->assertDatabaseHas('activity_logs', [
        'type' => 'info',
        'description' => 'Two-factor authentication enabled',
        'user_id' => $user->id,
    ]);
});
