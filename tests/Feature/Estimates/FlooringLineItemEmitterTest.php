<?php

use App\Enums\EstimateStatus;
use App\Enums\LineItemCategory;
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

test('emits install line items grouped by material', function () {
    $estimate = emitterEstimate(
        roomConfigs: [
            ['sqft' => 200, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
            ['sqft' => 150, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
            ['sqft' => 100, 'answers' => ['material' => 'tile', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
        ],
        projectWide: ['customer_type' => 'person', 'demo_haul_away' => 'none', 'baseboards' => 'leave', 'quarter_round' => 'none', 'transitions' => 0, 'door_undercuts' => 0, 'toilet_pulls' => 0],
    );

    $items = app(FlooringLineItemEmitter::class)->emit($estimate);

    $lvp = findDraft($items, 'install_lvp');
    $tile = findDraft($items, 'install_tile');

    expect($lvp)->not->toBeNull()
        ->and($lvp->quantity)->toBe(350.0)
        ->and($lvp->unit)->toBe(LineItemUnit::Sqft)
        ->and($lvp->category)->toBe(LineItemCategory::Install)
        ->and($tile)->not->toBeNull()
        ->and($tile->quantity)->toBe(100.0);
});

test('emits demo line items for non-bare existing floors', function () {
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
        ->and($tile)->not->toBeNull()
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

test('emits subfloor prep items and skips none', function () {
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
        ->and($major)->not->toBeNull()
        ->and($major->quantity)->toBe(300.0);
});

test('emits furniture move and replace items', function () {
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

test('emits baseboard line items and skips leave', function () {
    $estimate = emitterEstimate(
        roomConfigs: [
            ['sqft' => 200, 'linear_feet' => 60, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
            ['sqft' => 100, 'linear_feet' => 40, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
        ],
        projectWide: ['customer_type' => 'person', 'demo_haul_away' => 'none', 'baseboards' => 'remove_replace', 'quarter_round' => 'none', 'transitions' => 0, 'door_undercuts' => 0, 'toilet_pulls' => 0],
    );

    $items = app(FlooringLineItemEmitter::class)->emit($estimate);
    $baseboards = findDraft($items, 'baseboards_remove_replace');

    expect($baseboards)->not->toBeNull()
        ->and($baseboards->quantity)->toBe(100.0)
        ->and($baseboards->unit)->toBe(LineItemUnit::LinearFeet)
        ->and($baseboards->category)->toBe(LineItemCategory::Trim);

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

test('emits quarter round for new and reuse, skips none', function () {
    $estimate = emitterEstimate(
        roomConfigs: [
            ['sqft' => 200, 'linear_feet' => 60, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
        ],
        projectWide: ['customer_type' => 'person', 'demo_haul_away' => 'none', 'baseboards' => 'leave', 'quarter_round' => 'new', 'transitions' => 0, 'door_undercuts' => 0, 'toilet_pulls' => 0],
    );

    $items = app(FlooringLineItemEmitter::class)->emit($estimate);
    $qr = findDraft($items, 'quarter_round_new');

    expect($qr)->not->toBeNull()
        ->and($qr->quantity)->toBe(60.0)
        ->and($qr->unit)->toBe(LineItemUnit::LinearFeet);
});

test('emits transitions when count is positive', function () {
    $estimate = emitterEstimate(
        roomConfigs: [
            ['sqft' => 200, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
        ],
        projectWide: ['customer_type' => 'person', 'demo_haul_away' => 'none', 'baseboards' => 'leave', 'quarter_round' => 'none', 'transitions' => 3, 'door_undercuts' => 0, 'toilet_pulls' => 0],
    );

    $items = app(FlooringLineItemEmitter::class)->emit($estimate);
    $transitions = findDraft($items, 'transitions');

    expect($transitions)->not->toBeNull()
        ->and($transitions->quantity)->toBe(3.0)
        ->and($transitions->unit)->toBe(LineItemUnit::Each)
        ->and($transitions->category)->toBe(LineItemCategory::Trim);
});

test('emits door undercuts and toilet pulls when positive', function () {
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
        ->category->toBe(LineItemCategory::Services);

    expect(findDraft($items, 'toilet_pulls'))
        ->not->toBeNull()
        ->quantity->toBe(2.0);
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

test('emits haul-away for van and dumpster, skips none', function () {
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

test('empty answers emit only install items', function () {
    $estimate = emitterEstimate(
        roomConfigs: [
            ['sqft' => 200, 'answers' => ['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty']],
        ],
        projectWide: ['customer_type' => 'person', 'demo_haul_away' => 'none', 'baseboards' => 'leave', 'quarter_round' => 'none', 'transitions' => 0, 'door_undercuts' => 0, 'toilet_pulls' => 0],
    );

    $items = app(FlooringLineItemEmitter::class)->emit($estimate);

    expect($items)->toHaveCount(1);
    expect($items[0]->key)->toBe('install_lvp');
});
