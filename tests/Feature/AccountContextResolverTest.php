<?php

use App\Contexts\AccountContext;
use App\Enums\SecurityGroup;
use App\Models\Account;
use App\Models\SecurityGroupUser;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('resolver picks default account when no session value', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($user)->get(route('dashboard'));

    expect(session('current_account_id'))->toBe($account->id);
    expect(app(AccountContext::class)->get()->id)->toBe($account->id);
});

test('resolver uses valid session value', function () {
    $first = Account::factory()->create();
    $user = $first->owner;
    $second = Account::factory()->create(['owner_user_id' => User::factory()]);
    $second->users()->attach($user);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $second->id])
        ->get(route('dashboard'));

    expect(app(AccountContext::class)->get()->id)->toBe($second->id);
});

test('resolver clears stale session value and falls back to default', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $other = Account::factory()->create();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $other->id])
        ->get(route('dashboard'));

    expect(app(AccountContext::class)->get()->id)->toBe($account->id);
    expect(session('current_account_id'))->toBe($account->id);
});

test('resolver returns null for user with no memberships', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'));

    expect(app(AccountContext::class)->get())->toBeNull();
    expect(session('current_account_id'))->toBeNull();
});

test('switchTo rejects accounts the user is not a member of', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $other = Account::factory()->create();

    $context = app(AccountContext::class);

    $this->expectException(HttpException::class);
    $context->switchTo($user, $other);
});

test('switchTo persists new selection to session', function () {
    $first = Account::factory()->create();
    $user = $first->owner;
    $second = Account::factory()->create(['owner_user_id' => User::factory()]);
    $second->users()->attach($user);

    $this->actingAs($user)->get(route('dashboard'));

    $context = app(AccountContext::class);
    $context->switchTo($user, $second);

    expect(session('current_account_id'))->toBe($second->id);
    expect($context->get()->id)->toBe($second->id);
});

test('middleware integration persists session across requests', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $this->actingAs($user)->get(route('dashboard'));

    expect(session('current_account_id'))->toBe($account->id);

    $this->actingAs($user)->get(route('dashboard'));

    expect(app(AccountContext::class)->get()->id)->toBe($account->id);
});

test('admin in account A cannot access admin routes under account B', function () {
    $accountA = Account::factory()->create();
    $user = $accountA->owner;
    $accountB = Account::factory()->create(['owner_user_id' => User::factory()]);
    $accountB->users()->attach($user);

    SecurityGroupUser::create([
        'account_id' => $accountA->id,
        'user_id' => $user->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $accountA->id])
        ->get(route('admin.users.index'))
        ->assertOk();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $accountB->id])
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

test('inertia auth.security_groups reflects only current account groups', function () {
    $accountA = Account::factory()->create();
    $user = $accountA->owner;
    $accountB = Account::factory()->create(['owner_user_id' => User::factory()]);
    $accountB->users()->attach($user);

    SecurityGroupUser::create([
        'account_id' => $accountA->id,
        'user_id' => $user->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $accountA->id])
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.security_groups', ['admin']));

    $this->actingAs($user)
        ->withSession(['current_account_id' => $accountB->id])
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.security_groups', []));
});

test('admin user list is scoped to the current account', function () {
    $accountA = Account::factory()->create();
    $user = $accountA->owner;
    $accountB = Account::factory()->create(['owner_user_id' => User::factory()]);
    $accountB->users()->attach($user);

    User::factory()->forAccount($accountA)->create();
    User::factory()->forAccount($accountB)->create();

    SecurityGroupUser::create([
        'account_id' => $accountA->id,
        'user_id' => $user->id,
        'security_group' => SecurityGroup::Admin,
    ]);
    SecurityGroupUser::create([
        'account_id' => $accountB->id,
        'user_id' => $user->id,
        'security_group' => SecurityGroup::Admin,
    ]);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $accountA->id])
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('users', 2));

    $this->actingAs($user)
        ->withSession(['current_account_id' => $accountB->id])
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('users', 3));
});
