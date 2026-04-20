<?php

use App\Enums\ActivityEvent;
use App\Enums\SecurityGroup;
use App\Models\Account;
use App\Models\AccountDepositOverride;
use App\Models\ActivityLog;
use App\Models\SecurityGroupUser;
use App\Models\User;

beforeEach(function () {
    $this->account = Account::factory()->create();
    $this->admin = $this->account->owner;
    SecurityGroupUser::create([
        'account_id' => $this->account->id,
        'user_id' => $this->admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);
});

test('admins can view the deposits page with defaults', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.deposits.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/deposits/edit')
            ->where('override', null)
            ->where('defaults.material_deposit_percent', 100)
            ->where('defaults.labor_deposit_percent', 80)
            ->where('resolved.material_deposit_percent', 100)
            ->where('resolved.labor_deposit_percent', 80)
        );
});

test('non-admins cannot view the deposits page', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('admin.deposits.edit'))
        ->assertForbidden();
});

test('admins can save a custom override for both sides', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.deposits.update'), [
            'material_deposit_percent' => 50,
            'labor_deposit_percent' => 25,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('account_deposit_overrides', [
        'account_id' => $this->account->id,
        'material_deposit_percent' => 50,
        'labor_deposit_percent' => 25,
    ]);

    expect(ActivityLog::where('event', ActivityEvent::AccountDepositOverrideUpdated)->count())->toBe(1);
});

test('admins can save a partial override (one side only)', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.deposits.update'), [
            'material_deposit_percent' => '',
            'labor_deposit_percent' => 40,
        ])
        ->assertRedirect();

    $row = AccountDepositOverride::withoutGlobalScope('account')
        ->where('account_id', $this->account->id)
        ->first();

    expect($row->material_deposit_percent)->toBeNull();
    expect($row->labor_deposit_percent)->toBe(40);

    // Resolved values still fall back to default on the null side.
    $response = $this->actingAs($this->admin)
        ->get(route('admin.deposits.edit'))
        ->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->where('resolved.material_deposit_percent', 100)
        ->where('resolved.labor_deposit_percent', 40)
    );
});

test('saving again updates the existing override row', function () {
    AccountDepositOverride::factory()->create([
        'account_id' => $this->account->id,
        'material_deposit_percent' => 70,
        'labor_deposit_percent' => 60,
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.deposits.update'), [
            'material_deposit_percent' => 90,
            'labor_deposit_percent' => 50,
        ])
        ->assertRedirect();

    expect(AccountDepositOverride::withoutGlobalScope('account')
        ->where('account_id', $this->account->id)
        ->count())->toBe(1);

    $row = AccountDepositOverride::withoutGlobalScope('account')
        ->where('account_id', $this->account->id)
        ->first();
    expect($row->material_deposit_percent)->toBe(90);
    expect($row->labor_deposit_percent)->toBe(50);
});

test('admins can revert to defaults by deleting the override', function () {
    AccountDepositOverride::factory()->create([
        'account_id' => $this->account->id,
        'material_deposit_percent' => 50,
        'labor_deposit_percent' => 25,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('admin.deposits.destroy'))
        ->assertRedirect();

    $this->assertDatabaseMissing('account_deposit_overrides', [
        'account_id' => $this->account->id,
    ]);

    expect(ActivityLog::where('event', ActivityEvent::AccountDepositOverrideReverted)->count())->toBe(1);
});

test('percentages above 100 are rejected', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.deposits.update'), [
            'material_deposit_percent' => 200,
            'labor_deposit_percent' => 50,
        ])
        ->assertSessionHasErrors('material_deposit_percent');
});

test('negative percentages are rejected', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.deposits.update'), [
            'material_deposit_percent' => -5,
            'labor_deposit_percent' => 50,
        ])
        ->assertSessionHasErrors('material_deposit_percent');
});
