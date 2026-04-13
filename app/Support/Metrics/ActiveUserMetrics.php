<?php

namespace App\Support\Metrics;

use App\Enums\ActivityEvent;
use App\Models\ActivityLog;
use Carbon\CarbonImmutable;

class ActiveUserMetrics
{
    /**
     * Rolling 7-day active user counts for the last 7 days, oldest first.
     *
     * Each entry is the count of distinct users with a `user.logged_in`
     * event in the 7-day window ending at the end of that day.
     *
     * @return array<int, array{date: string, count: int}>
     */
    public static function rollingSevenDayWindow(?CarbonImmutable $asOf = null): array
    {
        $asOf = ($asOf ?? CarbonImmutable::now())->startOfDay();

        $windowSize = 7;
        $bucketCount = 7;

        $rangeStart = $asOf->subDays($bucketCount + $windowSize - 2)->startOfDay();
        $rangeEnd = $asOf->endOfDay();

        $rows = ActivityLog::query()
            ->where('event', ActivityEvent::UserLoggedIn)
            ->whereNotNull('user_id')
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->selectRaw('DATE(created_at) as day, user_id')
            ->distinct()
            ->get();

        /** @var array<string, array<int, true>> $byDay */
        $byDay = [];
        foreach ($rows as $row) {
            $byDay[$row->day][$row->user_id] = true;
        }

        $series = [];
        for ($i = $bucketCount - 1; $i >= 0; $i--) {
            $bucketEnd = $asOf->subDays($i);
            $bucketStart = $bucketEnd->subDays($windowSize - 1);

            $unique = [];
            for ($d = 0; $d < $windowSize; $d++) {
                $key = $bucketStart->addDays($d)->toDateString();
                if (isset($byDay[$key])) {
                    $unique += $byDay[$key];
                }
            }

            $series[] = [
                'date' => $bucketEnd->toDateString(),
                'count' => count($unique),
            ];
        }

        return $series;
    }
}
