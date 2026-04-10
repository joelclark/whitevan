<?php

use App\Enums\ActivityEvent;
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

test('info and error rows have a null event', function () {
    ActivityLogger::info('Ad-hoc info');
    ActivityLogger::error('Ad-hoc error');

    expect(ActivityLog::where('description', 'Ad-hoc info')->first()->event)->toBeNull()
        ->and(ActivityLog::where('description', 'Ad-hoc error')->first()->event)->toBeNull();
});

test('event method writes the event column and defaults description to the enum label', function () {
    ActivityLogger::event(ActivityEvent::UserLoggedIn);

    $log = ActivityLog::where('event', ActivityEvent::UserLoggedIn->value)->first();

    expect($log)->not->toBeNull()
        ->and($log->event)->toBe(ActivityEvent::UserLoggedIn)
        ->and($log->description)->toBe('User logged in')
        ->and($log->type)->toBe(ActivityLogType::Info);
});

test('event method accepts a description override', function () {
    ActivityLogger::event(
        ActivityEvent::UserSecurityGroupAdded,
        'Security group added: admin',
        ['security_group' => 'admin'],
    );

    $log = ActivityLog::where('event', ActivityEvent::UserSecurityGroupAdded->value)->first();

    expect($log->description)->toBe('Security group added: admin')
        ->and($log->metadata)->toBe(['security_group' => 'admin']);
});

test('event method passes account and user', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    ActivityLogger::event(ActivityEvent::UserLoggedIn, account: $account, user: $user);

    $this->assertDatabaseHas('activity_logs', [
        'event' => ActivityEvent::UserLoggedIn->value,
        'account_id' => $account->id,
        'user_id' => $user->id,
    ]);
});

test('event method writes to application log with the event value', function () {
    Log::spy();

    ActivityLogger::event(ActivityEvent::UserLoggedIn, metadata: ['ip' => '127.0.0.1']);

    Log::shouldHaveReceived('info')
        ->with('User logged in', ['event' => 'user.logged_in', 'metadata' => ['ip' => '127.0.0.1']])
        ->once();
});
