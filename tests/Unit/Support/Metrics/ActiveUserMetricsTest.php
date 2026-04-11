<?php

use App\Enums\ActivityEvent;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Metrics\ActiveUserMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function loginLog(User $user, CarbonImmutable $at): ActivityLog
{
    return ActivityLog::factory()->forUser($user)->create([
        'event' => ActivityEvent::UserLoggedIn,
        'created_at' => $at,
    ]);
}

test('returns 7 buckets oldest first ending on asOf', function () {
    $asOf = CarbonImmutable::parse('2026-04-10');

    $series = ActiveUserMetrics::rollingSevenDayWindow($asOf);

    expect($series)->toHaveCount(7);
    expect($series[0]['date'])->toBe('2026-04-04');
    expect($series[6]['date'])->toBe('2026-04-10');
});

test('empty database yields zero counts', function () {
    $series = ActiveUserMetrics::rollingSevenDayWindow(CarbonImmutable::parse('2026-04-10'));

    expect(collect($series)->pluck('count')->all())->toBe([0, 0, 0, 0, 0, 0, 0]);
});

test('a login today only appears in the window that ends today', function () {
    $asOf = CarbonImmutable::parse('2026-04-10');
    $user = User::factory()->create();
    loginLog($user, $asOf->setTime(9, 0));

    $series = ActiveUserMetrics::rollingSevenDayWindow($asOf);

    expect(collect($series)->pluck('count')->all())->toBe([0, 0, 0, 0, 0, 0, 1]);
});

test('repeat logins by the same user count once per window', function () {
    $asOf = CarbonImmutable::parse('2026-04-10');
    $user = User::factory()->create();
    loginLog($user, $asOf);
    loginLog($user, $asOf->subHours(3));
    loginLog($user, $asOf->subDays(1));
    loginLog($user, $asOf->subDays(2));
    loginLog($user, $asOf->subDays(3));

    $series = ActiveUserMetrics::rollingSevenDayWindow($asOf);

    expect($series[6]['count'])->toBe(1);
});

test('login 6 days ago is in todays window but 7 days ago is not', function () {
    $asOf = CarbonImmutable::parse('2026-04-10');
    $sixDaysAgo = User::factory()->create();
    $sevenDaysAgo = User::factory()->create();

    loginLog($sixDaysAgo, $asOf->subDays(6)->setTime(12, 0));
    loginLog($sevenDaysAgo, $asOf->subDays(7)->setTime(12, 0));

    $series = ActiveUserMetrics::rollingSevenDayWindow($asOf);

    expect($series[6]['count'])->toBe(1);
    expect($series[5]['count'])->toBe(2);
});

test('logins outside the 13 day range are excluded', function () {
    $asOf = CarbonImmutable::parse('2026-04-10');
    $user = User::factory()->create();
    loginLog($user, $asOf->subDays(20));

    $series = ActiveUserMetrics::rollingSevenDayWindow($asOf);

    expect(collect($series)->pluck('count')->all())->toBe([0, 0, 0, 0, 0, 0, 0]);
});

test('only user.logged_in events are counted', function () {
    $asOf = CarbonImmutable::parse('2026-04-10');
    $user = User::factory()->create();

    ActivityLog::factory()->forUser($user)->create([
        'event' => ActivityEvent::UserPasswordReset,
        'created_at' => $asOf,
    ]);

    $series = ActiveUserMetrics::rollingSevenDayWindow($asOf);

    expect($series[6]['count'])->toBe(0);
});
