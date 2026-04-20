<?php

use App\Enums\LineItemCategory;
use App\Enums\LineItemUnit;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\EstimateLineItem;

beforeEach(function () {
    $this->account = Account::factory()->create();
    $this->user = $this->account->owner;
    $customer = Customer::factory()->create(['account_id' => $this->account->id]);
    $this->estimate = Estimate::factory()->forCustomer($customer)->create([
        'locked_at' => now(),
    ]);
    $this->lineItem = EstimateLineItem::factory()
        ->for($this->estimate)
        ->labor()
        ->create([
            'key' => 'install_lvp_labor',
            'label' => 'Install LVP — labor',
            'category' => LineItemCategory::Install,
            'unit' => LineItemUnit::Sqft,
            'quantity' => '150',
            'unit_price' => '2.50',
        ]);
});

test('updateLineItem returns 409 when estimate is locked', function () {
    $this->actingAs($this->user)
        ->patch(route('estimates.line-items.update', [$this->estimate, $this->lineItem]), [
            'unit_price' => 3.00,
        ])
        ->assertStatus(409);

    $this->lineItem->refresh();
    expect($this->lineItem->unit_price)->toBe('2.50');
});

test('notes update is also blocked on a locked estimate', function () {
    $material = EstimateLineItem::factory()
        ->for($this->estimate)
        ->material()
        ->create([
            'key' => 'install_lvp_material',
            'label' => 'LVP — materials',
            'category' => LineItemCategory::Install,
            'unit' => LineItemUnit::Sqft,
            'quantity' => '150',
            'unit_price' => '2.85',
            'notes' => 'Original notes',
        ]);

    $this->actingAs($this->user)
        ->patch(route('estimates.line-items.update', [$this->estimate, $material]), [
            'notes' => 'Tampered',
        ])
        ->assertStatus(409);

    $material->refresh();
    expect($material->notes)->toBe('Original notes');
});

test('edit page exposes is_locked and deposit_defaults', function () {
    $response = $this->actingAs($this->user)
        ->get(route('estimates.edit', $this->estimate))
        ->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->component('estimates/edit')
        ->where('is_locked', true)
        ->has('deposit_defaults.material_deposit_percent')
        ->has('deposit_defaults.labor_deposit_percent')
    );
});
