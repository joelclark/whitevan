<?php

use App\Enums\ActivityLogType;
use App\Jobs\WriteActivityLog;
use App\Models\Account;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Queue;

test('info method dispatches WriteActivityLog job', function () {
    Queue::fake();

    ActivityLogger::info('User signed up');

    Queue::assertPushed(WriteActivityLog::class, function (WriteActivityLog $job) {
        return $job->type === ActivityLogType::Info
            && $job->description === 'User signed up'
            && $job->accountId === null
            && $job->userId === null;
    });
});

test('error method dispatches WriteActivityLog job', function () {
    Queue::fake();

    ActivityLogger::error('Login lockout', ['attempts' => 5]);

    Queue::assertPushed(WriteActivityLog::class, function (WriteActivityLog $job) {
        return $job->type === ActivityLogType::Error
            && $job->description === 'Login lockout'
            && $job->metadata === ['attempts' => 5];
    });
});

test('record method passes account and user ids to job', function () {
    Queue::fake();

    $account = Account::factory()->create();
    $user = $account->owner;

    ActivityLogger::info('Test event', null, $account, $user);

    Queue::assertPushed(WriteActivityLog::class, function (WriteActivityLog $job) use ($account, $user) {
        return $job->accountId === $account->id
            && $job->userId === $user->id;
    });
});
