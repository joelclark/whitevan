<?php

use App\Enums\LineItemCategory;
use App\Enums\LineItemKind;
use App\Enums\LineItemUnit;
use App\Interviews\LineItemDraft;
use App\Interviews\LineItemReconciler;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Estimate;

function reconcilerEstimate(): Estimate
{
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);

    return Estimate::factory()->forCustomer($customer)->create();
}

test('creates line items with the kind from the draft', function () {
    $estimate = reconcilerEstimate();

    app(LineItemReconciler::class)->reconcile($estimate, [
        new LineItemDraft(
            key: 'install_lvp_material',
            label: 'LVP — materials',
            category: LineItemCategory::Install,
            kind: LineItemKind::Material,
            quantity: 150,
            unit: LineItemUnit::Sqft,
        ),
        new LineItemDraft(
            key: 'install_lvp_labor',
            label: 'Install LVP — labor',
            category: LineItemCategory::Install,
            kind: LineItemKind::Labor,
            quantity: 150,
            unit: LineItemUnit::Sqft,
        ),
    ]);

    $material = $estimate->lineItems()->where('key', 'install_lvp_material')->first();
    $labor = $estimate->lineItems()->where('key', 'install_lvp_labor')->first();

    expect($material->kind)->toBe(LineItemKind::Material);
    expect($labor->kind)->toBe(LineItemKind::Labor);
});

test('preserves unit_price across re-emissions of the same key/kind', function () {
    $estimate = reconcilerEstimate();
    $reconciler = app(LineItemReconciler::class);

    $draft = new LineItemDraft(
        key: 'install_lvp_material',
        label: 'LVP — materials',
        category: LineItemCategory::Install,
        kind: LineItemKind::Material,
        quantity: 150,
        unit: LineItemUnit::Sqft,
    );

    $reconciler->reconcile($estimate, [$draft]);
    $estimate->lineItems()->first()->update(['unit_price' => '2.85']);

    $reconciler->reconcile($estimate, [$draft]);
    $row = $estimate->lineItems()->first();

    expect($row->unit_price)->toBe('2.85');
    expect($row->kind)->toBe(LineItemKind::Material);
});

test('deprecates items no longer in the emission', function () {
    $estimate = reconcilerEstimate();
    $reconciler = app(LineItemReconciler::class);

    $reconciler->reconcile($estimate, [
        new LineItemDraft(
            key: 'install_lvp_material',
            label: 'LVP — materials',
            category: LineItemCategory::Install,
            kind: LineItemKind::Material,
            quantity: 150,
            unit: LineItemUnit::Sqft,
        ),
    ]);

    $reconciler->reconcile($estimate, []);

    $row = $estimate->lineItems()->first();
    expect($row->deprecated_at)->not->toBeNull();
});
