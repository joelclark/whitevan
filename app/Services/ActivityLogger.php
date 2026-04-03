<?php

namespace App\Services;

use App\Enums\ActivityLogType;
use App\Jobs\WriteActivityLog;
use App\Models\Account;
use App\Models\User;

/**
 * Public API for recording activity log events.
 *
 * All writes are dispatched to a queued job so callers never block on the DB insert.
 *
 * Usage:
 *   ActivityLogger::info('User signed up', ['ip' => $ip], $account, $user);
 *   ActivityLogger::error('Login lockout', ['attempts' => 5], user: $user);
 */
class ActivityLogger
{
    /**
     * Record an activity log event.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public static function record(
        ActivityLogType $type,
        string $description,
        ?array $metadata = null,
        ?Account $account = null,
        ?User $user = null,
    ): void {
        WriteActivityLog::dispatch(
            type: $type,
            description: $description,
            metadata: $metadata,
            accountId: $account?->id,
            userId: $user?->id,
        );
    }

    /**
     * Record an informational event.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public static function info(
        string $description,
        ?array $metadata = null,
        ?Account $account = null,
        ?User $user = null,
    ): void {
        static::record(ActivityLogType::Info, $description, $metadata, $account, $user);
    }

    /**
     * Record an error event.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public static function error(
        string $description,
        ?array $metadata = null,
        ?Account $account = null,
        ?User $user = null,
    ): void {
        static::record(ActivityLogType::Error, $description, $metadata, $account, $user);
    }
}
