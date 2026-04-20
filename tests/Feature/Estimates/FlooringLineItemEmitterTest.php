<?php

use App\Enums\EstimateStatus;
use App\Enums\LineItemCategory;
use App\Enums\LineItemKind;
use App\Enums\LineItemUnit;
use App\Enums\Trade;
use App\Interviews\LineItemDraft;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\EstimateRoom;
use App\Trades\Flooring\LineItems\FlooringLineItemEmitter;

function emitterEstimate(array $roomConfigs, array $projectWide = []): Estimate
{
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create([
        'status' => EstimateStatus::Ready,
        'trade' => Trade::Flooring,
    ]);

    $roomAnswers = [];
    foreach ($roomConfigs as $i => $config) {
        $room = EstimateRoom::factory()->create([
            'estimate_id' => $estimate->id,
            'position' => $i + 1,
            'name' => $config['name'] ?? 'Room '.($i + 1),
            'sqft' => $config['sqft'],
            'linear_feet' => $config['linear_feet'] ?? 40,
        ]);
        $roomAnswers[(string) $room->id] = $config['answers'];
    }

    $estimate->forceFill([
        'interview_answers' => [
            'rooms' => $roomAnswers,
            'project_wide' => $projectWide,
        ],
    ])->save();

    return $estimate->refresh();
}

function findDraft(array $items, string $key): ?LineItemDraft
{
    foreach ($items as $item) {
        if ($item->key === $key) {
            return $item;
        }
    }

    return null;
}

test('emits install line items split into material and labor per material', function () {
    $estimate = emitterEstimate(
        roomConfigs: [
            ['sqft' => 200, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
            ['sqft' => 150, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
            ['sqft' => 100, 'answers' => ['material' => 'tile', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
        ],
        projectWide: ['customer_type' => 'person', 'demo_haul_away' => 'none', 'baseboards' => 'leave', 'quarter_round' => 'none', 'transitions' => 0, 'door_undercuts' => 0, 'toilet_pulls' => 0],
    );

    $items = app(FlooringLineItemEmitter::class)->emit($estimate);

    $lvpMaterial = findDraft($items, 'install_lvp_material');
    $lvpLabor = findDraft($items, 'install_lvp_labor');
    $tileMaterial = findDraft($items, 'install_tile_material');
    $tileLabor = findDraft($items, 'install_tile_labor');

    // Material quantities carry 10% cut waste, rounded up to the nearest 10.
    // Labor stays at the raw base quantity.
    expect($lvpMaterial)->not->toBeNull()
        ->and($lvpMaterial->kind)->toBe(LineItemKind::Material)
        ->and($lvpMaterial->quantity)->toBe(390.0) // 350 * 1.1 = 385 → 390
        ->and($lvpMaterial->unit)->toBe(LineItemUnit::Sqft)
        ->and($lvpMaterial->category)->toBe(LineItemCategory::Install)
        ->and($lvpLabor)->not->toBeNull()
        ->and($lvpLabor->kind)->toBe(LineItemKind::Labor)
        ->and($lvpLabor->quantity)->toBe(350.0)
        ->and($tileMaterial)->not->toBeNull()
        ->and($tileMaterial->kind)->toBe(LineItemKind::Material)
        ->and($tileMaterial->quantity)->toBe(110.0) // 100 * 1.1 = 110 → 110
        ->and($tileLabor)->not->toBeNull()
        ->and($tileLabor->kind)->toBe(LineItemKind::Labor)
        ->and($tileLabor->quantity)->toBe(100.0);
});

test('emits demo line items for non-bare existing floors as labor', function () {
    $estimate = emitterEstimate(
        roomConfigs: [
            ['sqft' => 200, 'answers' => ['material' => 'lvp', 'existing' => 'carpet', 'subfloor' => 'none', 'furniture' => 'empty']],
            ['sqft' => 150, 'answers' => ['material' => 'lvp', 'existing' => 'carpet', 'subfloor' => 'none', 'furniture' => 'empty']],
            ['sqft' => 100, 'answers' => ['material' => 'tile', 'existing' => 'tile', 'subfloor' => 'none', 'furniture' => 'empty']],
        ],
        projectWide: ['customer_type' => 'person', 'demo_haul_away' => 'van', 'baseboards' => 'leave', 'quarter_round' => 'none', 'transitions' => 0, 'door_undercuts' => 0, 'toilet_pulls' => 0],
    );

    $items = app(FlooringLineItemEmitter::class)->emit($estimate);

    $carpet = findDraft($items, 'remove_carpet');
    $tile = findDraft($items, 'remove_tile');
    $bare = findDraft($items, 'remove_bare');

    expect($carpet)->not->toBeNull()
        ->and($carpet->quantity)->toBe(350.0)
        ->and($carpet->category)->toBe(LineItemCategory::Demo)
        ->and($carpet->kind)->toBe(LineItemKind::Labor)
        ->and($tile)->not->toBeNull()
        ->and($tile->kind)->toBe(LineItemKind::Labor)
        ->and($tile->quantity)->toBe(100.0)
        ->and($bare)->toBeNull();
});

test('skips demo when existing is bare', function () {
    $estimate = emitterEstimate(
        roomConfigs: [
            ['sqft' => 200, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
        ],
        projectWide: ['customer_type' => 'person', 'demo_haul_away' => 'none', 'baseboards' => 'leave', 'quarter_round' => 'none', 'transitions' => 0, 'door_undercuts' => 0, 'toilet_pulls' => 0],
    );

    $items = app(FlooringLineItemEmitter::class)->emit($estimate);
    $demoItems = array_filter($items, fn ($i) => $i->category === LineItemCategory::Demo);

    expect($demoItems)->toBeEmpty();
});

test('emits subfloor prep items as labor and skips none', function () {
    $estimate = emitterEstimate(
        roomConfigs: [
            ['sqft' => 200, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'minor_patch', 'furniture' => 'empty']],
            ['sqft' => 300, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'major_self_level', 'furniture' => 'empty']],
            ['sqft' => 100, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
        ],
        projectWide: ['customer_type' => 'person', 'demo_haul_away' => 'none', 'baseboards' => 'leave', 'quarter_round' => 'none', 'transitions' => 0, 'door_undercuts' => 0, 'toilet_pulls' => 0],
    );

    $items = app(FlooringLineItemEmitter::class)->emit($estimate);

    $minor = findDraft($items, 'subfloor_minor_patch');
    $major = findDraft($items, 'subfloor_major_self_level');

    expect($minor)->not->toBeNull()
        ->and($minor->quantity)->toBe(200.0)
        ->and($minor->category)->toBe(LineItemCategory::Prep)
        ->and($minor->kind)->toBe(LineItemKind::Labor)
        ->and($major)->not->toBeNull()
        ->and($major->kind)->toBe(LineItemKind::Labor)
        ->and($major->quantity)->toBe(300.0);
});

test('emits furniture move and replace items as labor', function () {
    $estimate = emitterEstimate(
        roomConfigs: [
            ['sqft' => 200, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'move_replace']],
            ['sqft' => 200, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'move_replace']],
        ],
        projectWide: ['customer_type' => 'person', 'demo_haul_away' => 'none', 'baseboards' => 'leave', 'quarter_round' => 'none', 'transitions' => 0, 'door_undercuts' => 0, 'toilet_pulls' => 0],
    );

    $items = app(FlooringLineItemEmitter::class)->emit($estimate);
    $moveReplace = findDraft($items, 'furniture_move_replace');

    expect($moveReplace)->not->toBeNull()
        ->and($moveReplace->quantity)->toBe(2.0)
        ->and($moveReplace->kind)->toBe(LineItemKind::Labor)
        ->and($moveReplace->unit)->toBe(LineItemUnit::Each);
});

test('emits heavy furniture items with summed heavy_count', function () {
    $estimate = emitterEstimate(
        roomConfigs: [
            ['sqft' => 200, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'heavy', 'heavy_count' => 2]],
            ['sqft' => 200, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'heavy', 'heavy_count' => 3]],
        ],
        projectWide: ['customer_type' => 'person', 'demo_haul_away' => 'none', 'baseboards' => 'leave', 'quarter_round' => 'none', 'transitions' => 0, 'door_undercuts' => 0, 'toilet_pulls' => 0],
    );

    $items = app(FlooringLineItemEmitter::class)->emit($estimate);
    $heavy = findDraft($items, 'furniture_heavy');

    expect($heavy)->not->toBeNull()
        ->and($heavy->quantity)->toBe(5.0)
        ->and($heavy->kind)->toBe(LineItemKind::Labor)
        ->and($heavy->unit)->toBe(LineItemUnit::Each)
        ->and($heavy->category)->toBe(LineItemCategory::Services);
});

test('skips furniture when empty', function () {
    $estimate = emitterEstimate(
        roomConfigs: [
            ['sqft' => 200, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
        ],
        projectWide: ['customer_type' => 'person', 'demo_haul_away' => 'none', 'baseboards' => 'leave', 'quarter_round' => 'none', 'transitions' => 0, 'door_undercuts' => 0, 'toilet_pulls' => 0],
    );

    $items = app(FlooringLineItemEmitter::class)->emit($estimate);
    $furnitureItems = array_filter($items, fn ($i) => str_starts_with($i->key, 'furniture_'));

    expect($furnitureItems)->toBeEmpty();
});

test('baseboard remove_replace emits material + labor pair; remove_reinstall stays labor-only', function () {
    $estimate = emitterEstimate(
        roomConfigs: [
            ['sqft' => 200, 'linear_feet' => 60, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
            ['sqft' => 100, 'linear_feet' => 40, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
        ],
        projectWide: ['customer_type' => 'person', 'demo_haul_away' => 'none', 'baseboards' => 'remove_replace', 'quarter_round' => 'none', 'transitions' => 0, 'door_undercuts' => 0, 'toilet_pulls' => 0],
    );

    $items = app(FlooringLineItemEmitter::class)->emit($estimate);

    $material = findDraft($items, 'baseboards_remove_replace_material');
    $labor = findDraft($items, 'baseboards_remove_replace_labor');

    expect($material)->not->toBeNull()
        ->and($material->kind)->toBe(LineItemKind::Material)
        ->and($material->quantity)->toBe(110.0) // 100 * 1.1 = 110 → 110
        ->and($material->unit)->toBe(LineItemUnit::LinearFeet)
        ->and($material->category)->toBe(LineItemCategory::Trim)
        ->and($labor)->not->toBeNull()
        ->and($labor->kind)->toBe(LineItemKind::Labor)
        ->and($labor->quantity)->toBe(100.0);

    $reinstallEstimate = emitterEstimate(
        roomConfigs: [
            ['sqft' => 200, 'linear_feet' => 60, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
        ],
        projectWide: ['customer_type' => 'person', 'demo_haul_away' => 'none', 'baseboards' => 'remove_reinstall', 'quarter_round' => 'none', 'transitions' => 0, 'door_undercuts' => 0, 'toilet_pulls' => 0],
    );

    $reinstallItems = app(FlooringLineItemEmitter::class)->emit($reinstallEstimate);
    $reinstall = findDraft($reinstallItems, 'baseboards_remove_reinstall');

    expect($reinstall)->not->toBeNull()
        ->and($reinstall->kind)->toBe(LineItemKind::Labor);
    expect(findDraft($reinstallItems, 'baseboards_remove_reinstall_material'))->toBeNull();

    // Leave should emit nothing
    $estimate2 = emitterEstimate(
        roomConfigs: [
            ['sqft' => 200, 'linear_feet' => 60, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
        ],
        projectWide: ['customer_type' => 'person', 'demo_haul_away' => 'none', 'baseboards' => 'leave', 'quarter_round' => 'none', 'transitions' => 0, 'door_undercuts' => 0, 'toilet_pulls' => 0],
    );

    $items2 = app(FlooringLineItemEmitter::class)->emit($estimate2);
    $baseboardItems = array_filter($items2, fn ($i) => str_starts_with($i->key, 'baseboards_'));
    expect($baseboardItems)->toBeEmpty();
});

test('quarter round new splits material + labor; reuse stays labor-only', function () {
    $newEstimate = emitterEstimate(
        roomConfigs: [
            ['sqft' => 200, 'linear_feet' => 60, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
        ],
        projectWide: ['customer_type' => 'person', 'demo_haul_away' => 'none', 'baseboards' => 'leave', 'quarter_round' => 'new', 'transitions' => 0, 'door_undercuts' => 0, 'toilet_pulls' => 0],
    );

    $newItems = app(FlooringLineItemEmitter::class)->emit($newEstimate);
    $qrMaterial = findDraft($newItems, 'quarter_round_new_material');
    $qrLabor = findDraft($newItems, 'quarter_round_new_labor');

    expect($qrMaterial)->not->toBeNull()
        ->and($qrMaterial->kind)->toBe(LineItemKind::Material)
        ->and($qrMaterial->quantity)->toBe(70.0) // 60 * 1.1 = 66 → 70
        ->and($qrMaterial->unit)->toBe(LineItemUnit::LinearFeet)
        ->and($qrLabor)->not->toBeNull()
        ->and($qrLabor->kind)->toBe(LineItemKind::Labor)
        ->and($qrLabor->quantity)->toBe(60.0);

    $reuseEstimate = emitterEstimate(
        roomConfigs: [
            ['sqft' => 200, 'linear_feet' => 60, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
        ],
        projectWide: ['customer_type' => 'person', 'demo_haul_away' => 'none', 'baseboards' => 'leave', 'quarter_round' => 'reuse', 'transitions' => 0, 'door_undercuts' => 0, 'toilet_pulls' => 0],
    );

    $reuseItems = app(FlooringLineItemEmitter::class)->emit($reuseEstimate);
    $reuse = findDraft($reuseItems, 'quarter_round_reuse');

    expect($reuse)->not->toBeNull()->and($reuse->kind)->toBe(LineItemKind::Labor);
    expect(findDraft($reuseItems, 'quarter_round_reuse_material'))->toBeNull();
});

test('transitions split into material + labor when count is positive', function () {
    $estimate = emitterEstimate(
        roomConfigs: [
            ['sqft' => 200, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
        ],
        projectWide: ['customer_type' => 'person', 'demo_haul_away' => 'none', 'baseboards' => 'leave', 'quarter_round' => 'none', 'transitions' => 3, 'door_undercuts' => 0, 'toilet_pulls' => 0],
    );

    $items = app(FlooringLineItemEmitter::class)->emit($estimate);
    $material = findDraft($items, 'transitions_material');
    $labor = findDraft($items, 'transitions_labor');

    // Each units (transitions) are discrete pieces with no cut waste —
    // material and labor both carry the raw count.
    expect($material)->not->toBeNull()
        ->and($material->kind)->toBe(LineItemKind::Material)
        ->and($material->quantity)->toBe(3.0)
        ->and($material->unit)->toBe(LineItemUnit::Each)
        ->and($material->category)->toBe(LineItemCategory::Trim)
        ->and($labor)->not->toBeNull()
        ->and($labor->kind)->toBe(LineItemKind::Labor)
        ->and($labor->quantity)->toBe(3.0);
});

test('emits door undercuts and toilet pulls as labor when positive', function () {
    $estimate = emitterEstimate(
        roomConfigs: [
            ['sqft' => 200, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
        ],
        projectWide: ['customer_type' => 'person', 'demo_haul_away' => 'none', 'baseboards' => 'leave', 'quarter_round' => 'none', 'transitions' => 0, 'door_undercuts' => 4, 'toilet_pulls' => 2],
    );

    $items = app(FlooringLineItemEmitter::class)->emit($estimate);

    expect(findDraft($items, 'door_undercuts'))
        ->not->toBeNull()
        ->quantity->toBe(4.0)
        ->unit->toBe(LineItemUnit::Each)
        ->kind->toBe(LineItemKind::Labor)
        ->category->toBe(LineItemCategory::Services);

    expect(findDraft($items, 'toilet_pulls'))
        ->not->toBeNull()
        ->quantity->toBe(2.0)
        ->kind->toBe(LineItemKind::Labor);
});

test('skips door undercuts and toilet pulls when zero', function () {
    $estimate = emitterEstimate(
        roomConfigs: [
            ['sqft' => 200, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
        ],
        projectWide: ['customer_type' => 'person', 'demo_haul_away' => 'none', 'baseboards' => 'leave', 'quarter_round' => 'none', 'transitions' => 0, 'door_undercuts' => 0, 'toilet_pulls' => 0],
    );

    $items = app(FlooringLineItemEmitter::class)->emit($estimate);

    expect(findDraft($items, 'door_undercuts'))->toBeNull();
    expect(findDraft($items, 'toilet_pulls'))->toBeNull();
});

test('emits haul-away as labor for van and dumpster, skips none', function () {
    $estimate = emitterEstimate(
        roomConfigs: [
            ['sqft' => 200, 'answers' => ['material' => 'lvp', 'existing' => 'carpet', 'subfloor' => 'none', 'furniture' => 'empty']],
        ],
        projectWide: ['customer_type' => 'person', 'demo_haul_away' => 'dumpster', 'baseboards' => 'leave', 'quarter_round' => 'none', 'transitions' => 0, 'door_undercuts' => 0, 'toilet_pulls' => 0],
    );

    $items = app(FlooringLineItemEmitter::class)->emit($estimate);
    $haulAway = findDraft($items, 'haul_away_dumpster');

    expect($haulAway)->not->toBeNull()
        ->and($haulAway->quantity)->toBe(1.0)
        ->and($haulAway->unit)->toBe(LineItemUnit::Each)
        ->and($haulAway->kind)->toBe(LineItemKind::Labor)
        ->and($haulAway->category)->toBe(LineItemCategory::Services);
});

test('customer_type does not emit a line item', function () {
    $estimate = emitterEstimate(
        roomConfigs: [
            ['sqft' => 200, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
        ],
        projectWide: ['customer_type' => 'business', 'demo_haul_away' => 'none', 'baseboards' => 'leave', 'quarter_round' => 'none', 'transitions' => 0, 'door_undercuts' => 0, 'toilet_pulls' => 0],
    );

    $items = app(FlooringLineItemEmitter::class)->emit($estimate);
    $customerType = findDraft($items, 'customer_type');

    expect($customerType)->toBeNull();
});

test('material cut-waste: sqft and linear feet bump by 10% and round up to the next 10', function () {
    // 237 sqft → 237 * 1.1 = 260.7 → rounds up to 270
    $estimate = emitterEstimate(
        roomConfigs: [
            ['sqft' => 237, 'linear_feet' => 73, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
        ],
        projectWide: ['customer_type' => 'person', 'demo_haul_away' => 'none', 'baseboards' => 'remove_replace', 'quarter_round' => 'new', 'transitions' => 0, 'door_undercuts' => 0, 'toilet_pulls' => 0],
    );

    $items = app(FlooringLineItemEmitter::class)->emit($estimate);

    expect(findDraft($items, 'install_lvp_material')->quantity)->toBe(270.0);
    expect(findDraft($items, 'install_lvp_labor')->quantity)->toBe(237.0);

    // 73 lf → 73 * 1.1 = 80.3 → rounds up to 90
    expect(findDraft($items, 'baseboards_remove_replace_material')->quantity)->toBe(90.0);
    expect(findDraft($items, 'baseboards_remove_replace_labor')->quantity)->toBe(73.0);

    expect(findDraft($items, 'quarter_round_new_material')->quantity)->toBe(90.0);
    expect(findDraft($items, 'quarter_round_new_labor')->quantity)->toBe(73.0);
});

test('material cut-waste: an exact multiple of 10 stays put after the 10% bump', function () {
    // Boundary: 100 * 1.1 = 110 exactly — should NOT bump to 120 due to float drift.
    $estimate = emitterEstimate(
        roomConfigs: [
            ['sqft' => 100, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
        ],
        projectWide: ['customer_type' => 'person', 'demo_haul_away' => 'none', 'baseboards' => 'leave', 'quarter_round' => 'none', 'transitions' => 0, 'door_undercuts' => 0, 'toilet_pulls' => 0],
    );

    $items = app(FlooringLineItemEmitter::class)->emit($estimate);

    expect(findDraft($items, 'install_lvp_material')->quantity)->toBe(110.0);
});

test('empty answers emit only install material + labor pair', function () {
    $estimate = emitterEstimate(
        roomConfigs: [
            ['sqft' => 200, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
        ],
        projectWide: ['customer_type' => 'person', 'demo_haul_away' => 'none', 'baseboards' => 'leave', 'quarter_round' => 'none', 'transitions' => 0, 'door_undercuts' => 0, 'toilet_pulls' => 0],
    );

    $items = app(FlooringLineItemEmitter::class)->emit($estimate);

    expect($items)->toHaveCount(2);
    $keys = array_map(fn ($i) => $i->key, $items);
    expect($keys)->toContain('install_lvp_material', 'install_lvp_labor');
});
