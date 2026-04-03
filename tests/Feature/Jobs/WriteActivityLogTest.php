<?php

use App\Enums\ActivityLogType;
use App\Jobs\WriteActivityLog;
use App\Models\Account;
use App\Models\ActivityLog;

test('it creates an activity log entry', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    (new WriteActivityLog(
        type: ActivityLogType::Info,
        description: 'User signed up',
        metadata: ['ip' => '127.0.0.1'],
        accountId: $account->id,
        userId: $user->id,
    ))->handle();

    $this->assertDatabaseHas('activity_logs', [
        'type' => 'info',
        'description' => 'User signed up',
        'account_id' => $account->id,
        'user_id' => $user->id,
    ]);

    $log = ActivityLog::first();
    expect($log->metadata)->toBe(['ip' => '127.0.0.1']);
});

test('it creates entry with nullable relations', function () {
    (new WriteActivityLog(
        type: ActivityLogType::Error,
        description: 'Queue worker restarted',
    ))->handle();

    $this->assertDatabaseHas('activity_logs', [
        'type' => 'error',
        'description' => 'Queue worker restarted',
        'account_id' => null,
        'user_id' => null,
    ]);
});

test('it stores metadata as json', function () {
    (new WriteActivityLog(
        type: ActivityLogType::Info,
        description: 'Test event',
        metadata: ['key' => 'value', 'nested' => ['a' => 1]],
    ))->handle();

    $log = ActivityLog::first();
    expect($log->metadata)->toBe(['key' => 'value', 'nested' => ['a' => 1]]);
});
