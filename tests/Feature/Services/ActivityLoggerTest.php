<?php

use App\Enums\ActivityLogType;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Log;

test('info method creates an activity log entry', function () {
    ActivityLogger::info('User signed up');

    $this->assertDatabaseHas('activity_logs', [
        'type' => 'info',
        'description' => 'User signed up',
        'account_id' => null,
        'user_id' => null,
    ]);
});

test('error method creates an activity log entry', function () {
    ActivityLogger::error('Login lockout', ['attempts' => 5]);

    $log = ActivityLog::where('description', 'Login lockout')->first();

    expect($log->type)->toBe(ActivityLogType::Error)
        ->and($log->metadata)->toBe(['attempts' => 5]);
});

test('info method writes to application log', function () {
    Log::spy();

    ActivityLogger::info('User signed up', ['ip' => '127.0.0.1']);

    Log::shouldHaveReceived('info')
        ->with('User signed up', ['metadata' => ['ip' => '127.0.0.1']])
        ->once();
});

test('error method writes to application log', function () {
    Log::spy();

    ActivityLogger::error('Login lockout', ['attempts' => 5]);

    Log::shouldHaveReceived('error')
        ->with('Login lockout', ['metadata' => ['attempts' => 5]])
        ->once();
});

test('record method passes account and user ids', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    ActivityLogger::info('Test event', null, $account, $user);

    $this->assertDatabaseHas('activity_logs', [
        'description' => 'Test event',
        'account_id' => $account->id,
        'user_id' => $user->id,
    ]);
});
