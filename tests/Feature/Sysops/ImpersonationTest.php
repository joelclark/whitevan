<?php

use App\Contexts\ImpersonationContext;
use App\Enums\ActivityEvent;
use App\Enums\SecurityGroup;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\SecurityGroupUser;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Gate;

test('guests are redirected to login when starting impersonation', function () {
    $account = Account::factory()->create();

    $this->post(route('sysops.accounts.impersonate', $account))
        ->assertRedirect(route('login'));
});

test('non-sysop users get 403 when starting impersonation', function () {
    $account = Account::factory()->create();

    $this->actingAs($account->owner)
        ->post(route('sysops.accounts.impersonate', $account))
        ->assertForbidden();
});

test('admin of another account gets 403 when starting impersonation', function () {
    $account = Account::factory()->create();
    $admin = $account->owner;
    SecurityGroupUser::create([
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);
    $target = Account::factory()->create();

    $this->actingAs($admin)
        ->post(route('sysops.accounts.impersonate', $target))
        ->assertForbidden();
});

test('sysop can start impersonation', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();

    $this->actingAs($sysop)
        ->post(route('sysops.accounts.impersonate', $account))
        ->assertRedirect(route('dashboard'));

    expect(session('impersonated_account_id'))->toBe($account->id);
    expect(ActivityLog::where('event', 'sysop.impersonation_started')
        ->where('account_id', $account->id)
        ->where('user_id', $sysop->id)
        ->exists())->toBeTrue();
});

test('starting impersonation for a nonexistent account 404s', function () {
    $sysop = User::factory()->sysop()->create();

    $this->actingAs($sysop)
        ->post('/sysops/999999/impersonate')
        ->assertNotFound();
});

test('starting impersonation is blocked while already impersonating', function () {
    $sysop = User::factory()->sysop()->create();
    $first = Account::factory()->create();
    $second = Account::factory()->create();

    // Sysop routes 403 during an active impersonation session — the sysop
    // must stop the current impersonation before starting a new one.
    $this->actingAs($sysop)
        ->withSession(['impersonated_account_id' => $first->id])
        ->post(route('sysops.accounts.impersonate', $second))
        ->assertForbidden();

    expect(session('impersonated_account_id'))->toBe($first->id);
});

test('guests are redirected to login when stopping impersonation', function () {
    $this->delete(route('impersonate.stop'))
        ->assertRedirect(route('login'));
});

test('regular users get 403 when stopping impersonation', function () {
    $account = Account::factory()->create();

    $this->actingAs($account->owner)
        ->delete(route('impersonate.stop'))
        ->assertForbidden();
});

test('sysop with no active impersonation redirects back to accounts index', function () {
    $sysop = User::factory()->sysop()->create();

    $this->actingAs($sysop)
        ->delete(route('impersonate.stop'))
        ->assertRedirect(route('sysops.accounts.index'));

    expect(ActivityLog::where('event', 'sysop.impersonation_stopped')->exists())->toBeFalse();
});

test('sysop can stop impersonation', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();

    $this->actingAs($sysop)
        ->withSession(['impersonated_account_id' => $account->id])
        ->delete(route('impersonate.stop'))
        ->assertRedirect(route('sysops.accounts.show', $account->id));

    expect(session('impersonated_account_id'))->toBeNull();
    expect(ActivityLog::where('event', 'sysop.impersonation_stopped')
        ->where('account_id', $account->id)
        ->where('user_id', $sysop->id)
        ->exists())->toBeTrue();
});

test('auth inertia props swap while impersonating', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();

    $this->actingAs($sysop)
        ->withSession(['impersonated_account_id' => $account->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.is_sysop', false)
            ->where('auth.security_groups', ['admin'])
            ->where('auth.account.id', $account->id)
            ->where('auth.impersonating.account.id', $account->id)
            ->where('auth.impersonating.account.name', $account->name)
        );
});

test('not impersonating leaves auth.impersonating null', function () {
    $account = Account::factory()->create();

    $this->actingAs($account->owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.impersonating', null));
});

test('settings routes redirect to dashboard while impersonating', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();

    $routes = [
        route('profile.edit'),
        route('security.edit'),
        route('appearance.edit'),
    ];

    foreach ($routes as $url) {
        $this->actingAs($sysop)
            ->withSession(['impersonated_account_id' => $account->id])
            ->get($url)
            ->assertRedirect(route('dashboard'));
    }
});

test('settings mutation routes are blocked while impersonating', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();

    $this->actingAs($sysop)
        ->withSession(['impersonated_account_id' => $account->id])
        ->patch(route('profile.update'), ['name' => 'Evil', 'email' => 'evil@example.com'])
        ->assertRedirect(route('dashboard'));

    expect($sysop->fresh()->name)->not->toBe('Evil');
});

test('settings routes remain accessible when not impersonating', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk();
});

test('sysop routes 403 while impersonating', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();

    $this->actingAs($sysop)
        ->withSession(['impersonated_account_id' => $account->id])
        ->get(route('sysops.accounts.index'))
        ->assertForbidden();

    $this->actingAs($sysop)
        ->withSession(['impersonated_account_id' => $account->id])
        ->get(route('sysops.accounts.show', $account))
        ->assertForbidden();

    $this->actingAs($sysop)
        ->withSession(['impersonated_account_id' => $account->id])
        ->get(route('sysops.dashboard'))
        ->assertForbidden();
});

test('admin users page shows only the impersonated account users', function () {
    $sysop = User::factory()->sysop()->create();
    $accountA = Account::factory()->create();
    $accountB = Account::factory()->create();
    $aUser = User::factory()->for($accountA)->create(['name' => 'Alpha User']);
    $bUser = User::factory()->for($accountB)->create(['name' => 'Beta User']);

    $this->actingAs($sysop)
        ->withSession(['impersonated_account_id' => $accountA->id])
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/index')
            ->has('users', 2) // owner of accountA + $aUser
            ->where('users.0.account_id', $accountA->id)
            ->where('users.1.account_id', $accountA->id)
        );
});

test('gate allows manage-users while impersonating', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();

    $this->actingAs($sysop)
        ->withSession(['impersonated_account_id' => $account->id])
        ->get(route('admin.users.index'))
        ->assertOk();
});

test('sysop without impersonation still gets gate abilities', function () {
    $sysop = User::factory()->sysop()->create();

    expect(Gate::forUser($sysop)->allows('manage-users'))->toBeTrue();
});

test('impersonation authorization mirrors a real admin of the account', function () {
    // Regression guard for the privilege-boundary invariant: a sysop
    // impersonating an account must be authorized for exactly the abilities
    // a real Admin of that account would be authorized for — no more, no
    // less. Today SecurityGroup::Admin grants '*', so every ability
    // resolves to true on both sides. If someone later narrows Admin's
    // abilities (or adds a non-wildcard group), this test locks in that
    // the impersonation branch of Gate::before stays in lockstep.
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();
    $admin = User::factory()->for($account)->create();
    SecurityGroupUser::create([
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    app(ImpersonationContext::class)->start($account);

    $abilities = [
        'manage-users',
        'delete-account',
        'manage-billing',
        'totally-made-up-ability',
    ];

    foreach ($abilities as $ability) {
        expect(Gate::forUser($sysop)->allows($ability))
            ->toBe(
                Gate::forUser($admin)->allows($ability),
                "Ability [{$ability}] diverged: impersonating sysop does not match real admin.",
            );
    }
});

test('stale impersonation session id is cleared', function () {
    $sysop = User::factory()->sysop()->create();

    $this->actingAs($sysop)
        ->withSession(['impersonated_account_id' => 999999])
        ->get(route('sysops.accounts.index'))
        ->assertOk();

    expect(session('impersonated_account_id'))->toBeNull();
});

test('impersonating sysop can update security groups via admin route', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();
    $target = User::factory()->for($account)->create();

    $this->actingAs($sysop)
        ->withSession(['impersonated_account_id' => $account->id])
        ->put(route('admin.users.security-groups.update', $target), [
            'security_groups' => ['admin'],
        ])
        ->assertRedirect();

    expect(SecurityGroupUser::where('user_id', $target->id)->where('security_group', 'admin')->exists())->toBeTrue();

    $log = ActivityLog::where('event', 'user.security_group_added')->sole();
    expect($log->user_id)->toBe($sysop->id);
    expect($log->account_id)->toBe($account->id);
    expect($log->metadata['impersonated'] ?? null)->toBeTrue();
});

test('impersonating sysop can deactivate a user via admin route', function () {
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();
    $target = User::factory()->for($account)->create();

    $this->actingAs($sysop)
        ->withSession(['impersonated_account_id' => $account->id])
        ->put(route('admin.users.activation.update', $target), ['deactivated' => true])
        ->assertRedirect();

    expect($target->fresh()->deactivated_at)->not->toBeNull();

    $log = ActivityLog::where('event', 'user.deactivated')->sole();
    expect($log->user_id)->toBe($sysop->id);
    expect($log->account_id)->toBe($account->id);
    expect($log->metadata['impersonated'] ?? null)->toBeTrue();
});

test('activity logger stamps impersonated flag on every write during impersonation', function () {
    // This is the invariant test for centralized stamping: whichever path
    // writes a log — event(), info(), or error() — the impersonation flag
    // must appear automatically. This catches any future write path that
    // forgets to add the flag manually, because the flag is not added
    // manually anymore.
    $sysop = User::factory()->sysop()->create();
    $account = Account::factory()->create();

    app(ImpersonationContext::class)->start($account);

    ActivityLogger::event(ActivityEvent::UserLoggedIn, user: $sysop);
    ActivityLogger::info('ad hoc note', user: $sysop);
    ActivityLogger::error('ad hoc error', user: $sysop);

    $rows = ActivityLog::where('user_id', $sysop->id)->get();
    expect($rows)->toHaveCount(3);

    foreach ($rows as $row) {
        expect($row->metadata['impersonated'] ?? null)->toBeTrue();
    }
});

test('activity logger does not stamp impersonated flag when not impersonating', function () {
    $user = User::factory()->create();

    ActivityLogger::info('ad hoc note', user: $user);

    $row = ActivityLog::where('user_id', $user->id)->sole();
    expect($row->metadata['impersonated'] ?? null)->toBeNull();
});

test('normal admin mutations do not stamp impersonated flag', function () {
    $account = Account::factory()->create();
    $admin = $account->owner;
    SecurityGroupUser::create([
        'user_id' => $admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);
    $target = User::factory()->for($account)->create();

    $this->actingAs($admin)
        ->put(route('admin.users.activation.update', $target), ['deactivated' => true])
        ->assertRedirect();

    $log = ActivityLog::where('event', 'user.deactivated')->sole();
    expect($log->metadata)->not->toHaveKey('impersonated');
});

test('non-sysop with injected session key is ignored', function () {
    $account = Account::factory()->create();
    $otherAccount = Account::factory()->create();

    $this->actingAs($account->owner)
        ->withSession(['impersonated_account_id' => $otherAccount->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.impersonating', null));
});
