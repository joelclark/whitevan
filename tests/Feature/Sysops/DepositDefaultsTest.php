<?php

use App\Enums\ActivityEvent;
use App\Models\ActivityLog;
use App\Models\DepositDefaults;
use App\Models\User;

beforeEach(function () {
    $this->sysop = User::factory()->sysop()->create();
});

test('sysops can view the deposit settings page', function () {
    $this->actingAs($this->sysop)
        ->get(route('sysops.deposits.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sysops/deposits/edit')
            ->where('defaults.material_deposit_percent', 100)
            ->where('defaults.labor_deposit_percent', 80)
        );
});

test('non-sysops cannot view the deposit settings page', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('sysops.deposits.edit'))
        ->assertForbidden();
});

test('sysops can update the system deposit defaults', function () {
    $this->actingAs($this->sysop)
        ->put(route('sysops.deposits.update'), [
            'material_deposit_percent' => 90,
            'labor_deposit_percent' => 70,
        ])
        ->assertRedirect();

    $row = DepositDefaults::default();
    expect($row->material_deposit_percent)->toBe(90);
    expect($row->labor_deposit_percent)->toBe(70);

    expect(ActivityLog::where('event', ActivityEvent::DepositDefaultsUpdated)->count())->toBe(1);
});

test('percentages above 100 are rejected', function () {
    $this->actingAs($this->sysop)
        ->put(route('sysops.deposits.update'), [
            'material_deposit_percent' => 150,
            'labor_deposit_percent' => 80,
        ])
        ->assertSessionHasErrors('material_deposit_percent');
});

test('missing fields are rejected on the sysop page', function () {
    $this->actingAs($this->sysop)
        ->put(route('sysops.deposits.update'), [
            'material_deposit_percent' => 100,
        ])
        ->assertSessionHasErrors('labor_deposit_percent');
});
