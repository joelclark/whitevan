<?php

namespace App\Services;

use App\Enums\ActivityLogType;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Public API for recording activity log events.
 *
 * Writes are performed inline — the insert is lightweight enough that deferral
 * is unnecessary, and this works in both HTTP request and queued job contexts.
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
        ActivityLog::create([
            'type' => $type,
            'description' => $description,
            'metadata' => $metadata,
            'account_id' => $account?->id,
            'user_id' => $user?->id,
        ]);

        $context = array_filter([
            'metadata' => $metadata,
            'account_id' => $account?->id,
            'user_id' => $user?->id,
        ]);

        match ($type) {
            ActivityLogType::Info => Log::info($description, $context),
            ActivityLogType::Error => Log::error($description, $context),
        };
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
