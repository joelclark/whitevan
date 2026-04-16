<?php

use App\Enums\EstimateStatus;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\EstimateLineItem;
use App\Models\EstimateRoom;
use Illuminate\Support\Facades\Queue;

function reconciliationEstimate(?Account $account = null): Estimate
{
    $account ??= Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create([
        'status' => EstimateStatus::Ready,
    ]);

    EstimateRoom::factory()->create([
        'estimate_id' => $estimate->id,
        'position' => 1,
        'name' => 'Kitchen',
        'sqft' => 200,
        'linear_feet' => 60,
    ]);

    return $estimate->refresh();
}

function completeInterview(object $testCase, Estimate $estimate, Account $account, array $overrides = []): void
{
    $room = $estimate->rooms->first();
    $user = $account->owner;

    $defaults = [
        'material' => 'lvp',
        'existing' => 'carpet',
        'subfloor' => 'none',
        'furniture' => 'empty',
        'customer_type' => 'person',
        'demo_haul_away' => 'van',
        'baseboards' => 'leave',
        'quarter_round' => 'none',
        'transitions' => 0,
        'door_undercuts' => 0,
        'toilet_pulls' => 0,
    ];

    $answers = array_merge($defaults, $overrides);

    $post = fn (string $key, mixed $value, ?int $roomId = null) => $testCase->actingAs($user)
        ->post(route('estimates.interview.answer', $estimate), [
            'question_key' => $key,
            'room_id' => $roomId,
            'value' => $value,
        ])
        ->assertRedirect();

    $post('material', $answers['material'], $room->id);
    $post('existing', $answers['existing'], $room->id);
    $post('subfloor', $answers['subfloor'], $room->id);
    $post('furniture', $answers['furniture'], $room->id);

    if ($answers['furniture'] === 'heavy') {
        $post('heavy_count', $answers['heavy_count'] ?? 1, $room->id);
    }

    $post('customer_type', $answers['customer_type']);
    $post('demo_haul_away', $answers['demo_haul_away']);
    $post('baseboards', $answers['baseboards']);
    $post('quarter_round', $answers['quarter_round']);
    $post('transitions', $answers['transitions']);
    $post('door_undercuts', $answers['door_undercuts']);
    $post('toilet_pulls', $answers['toilet_pulls']);
}

test('changing an answer after completion deprecates the old line item and creates the new one', function () {
    $account = Account::factory()->create();
    $estimate = reconciliationEstimate($account);
    $room = $estimate->rooms->first();
    $user = $account->owner;

    completeInterview($this, $estimate, $account, ['material' => 'lvp']);

    $lvpItem = EstimateLineItem::where('estimate_id', $estimate->id)
        ->where('key', 'install_lvp')
        ->first();
    expect($lvpItem)->not->toBeNull();

    // Change material from LVP to tile
    $this->actingAs($user)
        ->post(route('estimates.interview.answer', $estimate), [
            'question_key' => 'material',
            'room_id' => $room->id,
            'value' => 'tile',
        ])
        ->assertRedirect();

    $lvpItem->refresh();
    expect($lvpItem->deprecated_at)->not->toBeNull();

    $tileItem = EstimateLineItem::where('estimate_id', $estimate->id)
        ->where('key', 'install_tile')
        ->first();
    expect($tileItem)->not->toBeNull()
        ->and($tileItem->deprecated_at)->toBeNull();
});

test('reverting an answer un-deprecates the item with its price intact', function () {
    $account = Account::factory()->create();
    $estimate = reconciliationEstimate($account);
    $room = $estimate->rooms->first();
    $user = $account->owner;

    completeInterview($this, $estimate, $account, ['material' => 'lvp']);

    $lvpItem = EstimateLineItem::where('estimate_id', $estimate->id)
        ->where('key', 'install_lvp')
        ->first();
    $lvpItem->update(['unit_price' => 4.50]);

    // Change to tile
    $this->actingAs($user)
        ->post(route('estimates.interview.answer', $estimate), [
            'question_key' => 'material',
            'room_id' => $room->id,
            'value' => 'tile',
        ])
        ->assertRedirect();

    $lvpItem->refresh();
    expect($lvpItem->deprecated_at)->not->toBeNull()
        ->and($lvpItem->unit_price)->toBe('4.50');

    // Revert back to LVP
    $this->actingAs($user)
        ->post(route('estimates.interview.answer', $estimate), [
            'question_key' => 'material',
            'room_id' => $room->id,
            'value' => 'lvp',
        ])
        ->assertRedirect();

    $lvpItem->refresh();
    expect($lvpItem->deprecated_at)->toBeNull()
        ->and($lvpItem->unit_price)->toBe('4.50');
});

test('deprecated items are excluded from the edit page payload', function () {
    $account = Account::factory()->create();
    $estimate = reconciliationEstimate($account);
    $room = $estimate->rooms->first();
    $user = $account->owner;

    completeInterview($this, $estimate, $account, ['material' => 'lvp']);

    // Change to tile — deprecates install_lvp
    $this->actingAs($user)
        ->post(route('estimates.interview.answer', $estimate), [
            'question_key' => 'material',
            'room_id' => $room->id,
            'value' => 'tile',
        ])
        ->assertRedirect();

    $response = $this->actingAs($user)
        ->get(route('estimates.edit', $estimate))
        ->assertOk();

    $lineItemKeys = collect($response->original->getData()['page']['props']['line_items'])
        ->pluck('key')
        ->all();

    expect($lineItemKeys)->toContain('install_tile')
        ->not->toContain('install_lvp');
});

test('updating price on a deprecated item returns 404', function () {
    $account = Account::factory()->create();
    $estimate = reconciliationEstimate($account);
    $room = $estimate->rooms->first();
    $user = $account->owner;

    completeInterview($this, $estimate, $account, ['material' => 'lvp']);

    $lvpItem = EstimateLineItem::where('estimate_id', $estimate->id)
        ->where('key', 'install_lvp')
        ->first();

    // Deprecate it
    $this->actingAs($user)
        ->post(route('estimates.interview.answer', $estimate), [
            'question_key' => 'material',
            'room_id' => $room->id,
            'value' => 'tile',
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->patch(route('estimates.line-items.update', [$estimate, $lvpItem]), [
            'unit_price' => 99.99,
        ])
        ->assertNotFound();
});

test('retry hard-deletes all items including deprecated', function () {
    $account = Account::factory()->create();
    $estimate = reconciliationEstimate($account);
    $room = $estimate->rooms->first();
    $user = $account->owner;

    completeInterview($this, $estimate, $account, ['material' => 'lvp']);

    // Deprecate one
    $this->actingAs($user)
        ->post(route('estimates.interview.answer', $estimate), [
            'question_key' => 'material',
            'room_id' => $room->id,
            'value' => 'tile',
        ])
        ->assertRedirect();

    $allCount = EstimateLineItem::where('estimate_id', $estimate->id)->count();
    $deprecatedCount = EstimateLineItem::where('estimate_id', $estimate->id)->whereNotNull('deprecated_at')->count();
    expect($allCount)->toBeGreaterThan(0)
        ->and($deprecatedCount)->toBeGreaterThan(0);

    Queue::fake();

    $this->actingAs($user)
        ->post(route('estimates.retry', $estimate))
        ->assertRedirect();

    expect(EstimateLineItem::where('estimate_id', $estimate->id)->count())->toBe(0);
});

test('quantity updates when the same item key is still emitted', function () {
    $account = Account::factory()->create();
    $estimate = reconciliationEstimate($account);
    $room = $estimate->rooms->first();
    $user = $account->owner;

    completeInterview($this, $estimate, $account, [
        'existing' => 'carpet',
        'demo_haul_away' => 'van',
    ]);

    $removeCarpet = EstimateLineItem::where('estimate_id', $estimate->id)
        ->where('key', 'remove_carpet')
        ->first();
    expect($removeCarpet->quantity)->toBe('200.00');

    // Change existing from carpet to tile — remove_carpet deprecated, remove_tile created
    $this->actingAs($user)
        ->post(route('estimates.interview.answer', $estimate), [
            'question_key' => 'existing',
            'room_id' => $room->id,
            'value' => 'tile',
        ])
        ->assertRedirect();

    $removeCarpet->refresh();
    expect($removeCarpet->deprecated_at)->not->toBeNull();

    $removeTile = EstimateLineItem::where('estimate_id', $estimate->id)
        ->where('key', 'remove_tile')
        ->first();
    expect($removeTile)->not->toBeNull()
        ->and($removeTile->quantity)->toBe('200.00')
        ->and($removeTile->deprecated_at)->toBeNull();
});
