<?php

namespace App\Services;

use App\Enums\ActivityEvent;
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
 *   ActivityLogger::event(ActivityEvent::UserLoggedIn, metadata: [...], user: $user);
 *   ActivityLogger::info('Ad-hoc note', ['ip' => $ip], $account, $user);
 *   ActivityLogger::error('Background job failure', ['attempts' => 5], user: $user);
 *
 * Prefer event() for anything that should be counted or filtered. info() and
 * error() remain for ad-hoc diagnostic logging where no stable event key fits;
 * those rows are written with event = null and are excluded from metric queries
 * that filter on the event column.
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
        ?ActivityEvent $event = null,
    ): void {
        ActivityLog::create([
            'type' => $type,
            'event' => $event,
            'description' => $description,
            'metadata' => $metadata,
            'account_id' => $account?->id,
            'user_id' => $user?->id,
        ]);

        $context = array_filter([
            'event' => $event?->value,
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
     * Record a typed event. Description defaults to the enum's label() and may
     * be overridden when extra context (like an interpolated value) is useful
     * for the human-facing audit UI.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public static function event(
        ActivityEvent $event,
        ?string $description = null,
        ?array $metadata = null,
        ?Account $account = null,
        ?User $user = null,
    ): void {
        static::record(
            ActivityLogType::Info,
            $description ?? $event->label(),
            $metadata,
            $account,
            $user,
            $event,
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
