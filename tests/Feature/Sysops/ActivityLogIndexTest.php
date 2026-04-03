<?php

use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\User;

test('guests are redirected to login', function () {
    $this->get('/sysops/activity-logs')->assertRedirect(route('login'));
});

test('non-sysop users get 403', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($user)
        ->get('/sysops/activity-logs')
        ->assertForbidden();
});

test('sysop users can access activity logs', function () {
    $sysop = User::factory()->sysop()->create();

    $this->actingAs($sysop)
        ->get('/sysops/activity-logs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sysops/activity-logs/index')
            ->has('activityLogs')
            ->has('filters')
        );
});

test('activity logs are paginated', function () {
    $sysop = User::factory()->sysop()->create();
    ActivityLog::factory()->count(55)->create();

    $this->actingAs($sysop)
        ->get('/sysops/activity-logs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activityLogs.data', 50)
            ->where('activityLogs.total', 55)
        );
});

test('activity logs can be filtered by type', function () {
    $sysop = User::factory()->sysop()->create();
    ActivityLog::factory()->info()->count(3)->create();
    ActivityLog::factory()->error()->count(2)->create();

    $this->actingAs($sysop)
        ->get('/sysops/activity-logs?type=error')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activityLogs.data', 2)
            ->where('activityLogs.data.0.type', 'error')
        );
});

test('search matches description', function () {
    $sysop = User::factory()->sysop()->create();
    ActivityLog::factory()->create(['description' => 'User signed up']);
    ActivityLog::factory()->create(['description' => 'Login lockout']);

    $this->actingAs($sysop)
        ->get('/sysops/activity-logs?search=signed')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activityLogs.data', 1)
            ->where('activityLogs.data.0.description', 'User signed up')
        );
});

test('search matches user name', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();
    $user = $account->owner;
    ActivityLog::factory()->forUser($user)->create(['description' => 'some event']);
    ActivityLog::factory()->create(['description' => 'unrelated event']);

    $this->actingAs($sysop)
        ->get('/sysops/activity-logs?search='.urlencode($user->name))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activityLogs.data', 1)
        );
});

test('search matches account name', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create(['name' => 'Acme Widgets']);
    ActivityLog::factory()->forAccount($account)->create(['description' => 'some event']);
    ActivityLog::factory()->create(['description' => 'unrelated event']);

    $this->actingAs($sysop)
        ->get('/sysops/activity-logs?search=Acme+Widgets')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activityLogs.data', 1)
        );
});

test('activity logs can be filtered by account', function () {
    $sysop = User::factory()->sysop()->create();
    $account1 = Account::factory()->create();
    $account2 = Account::factory()->create();
    ActivityLog::factory()->forAccount($account1)->count(2)->create();
    ActivityLog::factory()->forAccount($account2)->count(3)->create();

    $this->actingAs($sysop)
        ->get('/sysops/activity-logs?account='.$account1->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activityLogs.data', 2)
        );
});

test('activity logs are sorted by date descending by default', function () {
    $sysop = User::factory()->sysop()->create();
    $older = ActivityLog::factory()->create(['created_at' => now()->subHour()]);
    $newer = ActivityLog::factory()->create(['created_at' => now()]);

    $this->actingAs($sysop)
        ->get('/sysops/activity-logs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activityLogs.data.0.id', $newer->id)
            ->where('activityLogs.data.1.id', $older->id)
        );
});

test('activity logs can be sorted ascending', function () {
    $sysop = User::factory()->sysop()->create();
    $older = ActivityLog::factory()->create(['created_at' => now()->subHour()]);
    $newer = ActivityLog::factory()->create(['created_at' => now()]);

    $this->actingAs($sysop)
        ->get('/sysops/activity-logs?sort=oldest')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activityLogs.data.0.id', $older->id)
            ->where('activityLogs.data.1.id', $newer->id)
        );
});

test('activity logs default to last 24 hours', function () {
    $sysop = User::factory()->sysop()->create();
    ActivityLog::factory()->create(['created_at' => now()->subHours(2)]);
    ActivityLog::factory()->create(['created_at' => now()->subHours(25)]);

    $this->actingAs($sysop)
        ->get('/sysops/activity-logs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activityLogs.data', 1)
        );
});

test('period filter can show last 30 days', function () {
    $sysop = User::factory()->sysop()->create();
    ActivityLog::factory()->create(['created_at' => now()->subDays(10)]);
    ActivityLog::factory()->create(['created_at' => now()->subDays(31)]);

    $this->actingAs($sysop)
        ->get('/sysops/activity-logs?period=30d')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activityLogs.data', 1)
        );
});

test('period filter constrains to selected range', function () {
    $sysop = User::factory()->sysop()->create();
    ActivityLog::factory()->create(['created_at' => now()->subMinutes(30)]);
    ActivityLog::factory()->create(['created_at' => now()->subHours(2)]);

    $this->actingAs($sysop)
        ->get('/sysops/activity-logs?period=1h')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activityLogs.data', 1)
        );
});

test('activity logs include related user and account', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();
    $user = $account->owner;
    ActivityLog::factory()->forAccount($account)->forUser($user)->create();

    $this->actingAs($sysop)
        ->get('/sysops/activity-logs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activityLogs.data.0.account')
            ->where('activityLogs.data.0.account.name', $account->name)
            ->has('activityLogs.data.0.user')
            ->where('activityLogs.data.0.user.name', $user->name)
        );
});
