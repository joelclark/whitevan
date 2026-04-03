<?php

namespace App\Jobs;

use App\Enums\ActivityLogType;
use App\Models\ActivityLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class WriteActivityLog implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public ActivityLogType $type,
        public string $description,
        public ?array $metadata = null,
        public ?int $accountId = null,
        public ?int $userId = null,
    ) {}

    /**
     * Write the activity log entry to the database.
     */
    public function handle(): void
    {
        ActivityLog::create([
            'type' => $this->type,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
        ]);
    }
}
