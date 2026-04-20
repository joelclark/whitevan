<?php

use App\Enums\LineItemCategory;
use App\Enums\LineItemKind;
use App\Enums\LineItemUnit;
use App\Models\Account;
use App\Models\AccountDepositOverride;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\EstimateLineItem;
use App\Services\QuoteSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function makeSnapshotEstimate(): Estimate
{
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create();

    EstimateLineItem::factory()
        ->for($estimate)
        ->material()
        ->create([
            'key' => 'install_lvp_material',
            'label' => 'Install LVP — materials',
            'category' => LineItemCategory::Install,
            'unit' => LineItemUnit::Sqft,
            'quantity' => '150',
            'unit_price' => '2.85',
            'notes' => 'Mohawk SolidTech 20mil',
        ]);

    EstimateLineItem::factory()
        ->for($estimate)
        ->labor()
        ->create([
            'key' => 'install_lvp_labor',
            'label' => 'Install LVP — labor',
            'category' => LineItemCategory::Install,
            'unit' => LineItemUnit::Sqft,
            'quantity' => '150',
            'unit_price' => '2.50',
        ]);

    EstimateLineItem::factory()
        ->for($estimate)
        ->labor()
        ->create([
            'key' => 'demo_carpet',
            'label' => 'Demo carpet',
            'category' => LineItemCategory::Demo,
            'unit' => LineItemUnit::Sqft,
            'quantity' => '240',
            'unit_price' => '0.75',
        ]);

    return $estimate;
}

test('builds a canonical snapshot with subtotals and deposits', function () {
    $estimate = makeSnapshotEstimate();
    $snapshot = app(QuoteSnapshot::class)->build($estimate);

    expect($snapshot['material_percent'])->toBe(100);
    expect($snapshot['labor_percent'])->toBe(80);
    expect($snapshot['material_subtotal'])->toBe('427.50'); // 150 * 2.85
    expect($snapshot['labor_subtotal'])->toBe('555.00');    // 150 * 2.50 + 240 * 0.75
    expect($snapshot['grand_total'])->toBe('982.50');
    expect($snapshot['material_deposit'])->toBe('427.50');
    expect($snapshot['labor_deposit'])->toBe('444.00');
    expect($snapshot['deposit_total'])->toBe('871.50');
    expect($snapshot['items'])->toHaveCount(3);
});

test('hash is stable across calls when nothing changes', function () {
    $estimate = makeSnapshotEstimate();
    $service = app(QuoteSnapshot::class);

    $hashA = $service->hash($service->build($estimate));
    $hashB = $service->hash($service->build($estimate->fresh()));

    expect($hashA)->toBe($hashB);
});

test('hash changes when a unit price changes', function () {
    $estimate = makeSnapshotEstimate();
    $service = app(QuoteSnapshot::class);
    $before = $service->hash($service->build($estimate));

    $estimate->activeLineItems()->where('kind', LineItemKind::Material->value)
        ->first()
        ->update(['unit_price' => '3.50']);

    $after = $service->hash($service->build($estimate->fresh()));
    expect($after)->not->toBe($before);
});

test('hash changes when notes change on a material item', function () {
    $estimate = makeSnapshotEstimate();
    $service = app(QuoteSnapshot::class);
    $before = $service->hash($service->build($estimate));

    $estimate->activeLineItems()->where('kind', LineItemKind::Material->value)
        ->first()
        ->update(['notes' => 'Shaw Floorte Pro 30mil, driftwood']);

    $after = $service->hash($service->build($estimate->fresh()));
    expect($after)->not->toBe($before);
});

test('hash changes when an admin deposit override changes', function () {
    $estimate = makeSnapshotEstimate();
    $service = app(QuoteSnapshot::class);
    $before = $service->hash($service->build($estimate));

    AccountDepositOverride::factory()->create([
        'account_id' => $estimate->account_id,
        'material_deposit_percent' => 50,
        'labor_deposit_percent' => null,
    ]);

    $after = $service->hash($service->build($estimate->fresh()));
    expect($after)->not->toBe($before);
});

test('hash does not change when estimate title changes (not in hash input)', function () {
    $estimate = makeSnapshotEstimate();
    $service = app(QuoteSnapshot::class);
    $before = $service->hash($service->build($estimate));

    $estimate->update(['title' => 'Something completely different']);

    $after = $service->hash($service->build($estimate->fresh()));
    expect($after)->toBe($before);
});

test('hash changes when a new line item is added', function () {
    $estimate = makeSnapshotEstimate();
    $service = app(QuoteSnapshot::class);
    $before = $service->hash($service->build($estimate));

    EstimateLineItem::factory()->for($estimate)->labor()->create([
        'key' => 'haul_away',
        'label' => 'Haul-away',
        'category' => LineItemCategory::Services,
        'unit' => LineItemUnit::Each,
        'quantity' => '1',
        'unit_price' => '150',
    ]);

    $after = $service->hash($service->build($estimate->fresh()));
    expect($after)->not->toBe($before);
});

test('deprecated line items are excluded from the hash', function () {
    $estimate = makeSnapshotEstimate();
    $service = app(QuoteSnapshot::class);
    $before = $service->hash($service->build($estimate));

    $demo = $estimate->lineItems()->where('key', 'demo_carpet')->first();
    $demo->update(['deprecated_at' => now()]);

    $after = $service->hash($service->build($estimate->fresh()));
    expect($after)->not->toBe($before);
});
