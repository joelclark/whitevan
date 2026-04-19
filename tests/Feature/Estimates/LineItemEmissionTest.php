<?php

use App\Enums\EstimateStatus;
use App\Enums\LineItemCategory;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\EstimateLineItem;
use App\Models\EstimateRoom;
use Illuminate\Support\Facades\Queue;

function lineItemEstimate(?Account $account = null): Estimate
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

function answerAll(object $testCase, Estimate $estimate, Account $account): void
{
    $room = $estimate->rooms->first();
    $user = $account->owner;

    $post = fn (array $body) => $testCase->actingAs($user)
        ->post(route('estimates.interview.answer', $estimate), $body)
        ->assertRedirect();

    $post(['question_key' => 'material', 'room_id' => $room->id, 'value' => 'lvp']);
    $post(['question_key' => 'existing', 'room_id' => $room->id, 'value' => 'carpet']);
    $post(['question_key' => 'subfloor', 'room_id' => $room->id, 'value' => 'minor_patch']);
    $post(['question_key' => 'furniture', 'room_id' => $room->id, 'value' => 'heavy']);
    $post(['question_key' => 'heavy_count', 'room_id' => $room->id, 'value' => 2]);

    $post(['question_key' => 'customer_type', 'value' => 'person']);
    $post(['question_key' => 'demo_haul_away', 'value' => 'van']);
    $post(['question_key' => 'baseboards', 'value' => 'remove_replace']);
    $post(['question_key' => 'quarter_round', 'value' => 'new']);
    $post(['question_key' => 'transitions', 'value' => 2]);
    $post(['question_key' => 'door_undercuts', 'value' => 3]);
    $post(['question_key' => 'toilet_pulls', 'value' => 1]);
}

test('completing the interview persists line items', function () {
    $account = Account::factory()->create();
    $estimate = lineItemEstimate($account);

    answerAll($this, $estimate, $account);

    $lineItems = EstimateLineItem::where('estimate_id', $estimate->id)
        ->orderBy('position')
        ->get();

    expect($lineItems)->not->toBeEmpty();

    $keys = $lineItems->pluck('key')->all();
    expect($keys)->toContain('install_lvp_material')
        ->toContain('install_lvp_labor')
        ->toContain('remove_carpet')
        ->toContain('subfloor_minor_patch')
        ->toContain('furniture_heavy')
        ->toContain('baseboards_remove_replace_material')
        ->toContain('baseboards_remove_replace_labor')
        ->toContain('quarter_round_new_material')
        ->toContain('quarter_round_new_labor')
        ->toContain('transitions_material')
        ->toContain('transitions_labor')
        ->toContain('door_undercuts')
        ->toContain('toilet_pulls')
        ->toContain('haul_away_van');

    $installLvpLabor = $lineItems->firstWhere('key', 'install_lvp_labor');
    expect($installLvpLabor->quantity)->toBe('200.00')
        ->and($installLvpLabor->category)->toBe(LineItemCategory::Install);

    $heavy = $lineItems->firstWhere('key', 'furniture_heavy');
    expect($heavy->quantity)->toBe('2.00');

    expect($lineItems->pluck('position')->unique()->count())->toBe($lineItems->count());
});

test('retry wipes line items', function () {
    $account = Account::factory()->create();
    $estimate = lineItemEstimate($account);

    answerAll($this, $estimate, $account);
    expect(EstimateLineItem::where('estimate_id', $estimate->id)->count())->toBeGreaterThan(0);

    Queue::fake();

    $this->actingAs($account->owner)
        ->post(route('estimates.retry', $estimate))
        ->assertRedirect();

    expect(EstimateLineItem::where('estimate_id', $estimate->id)->count())->toBe(0);
});

test('line items are not created before interview is complete', function () {
    $account = Account::factory()->create();
    $estimate = lineItemEstimate($account);
    $room = $estimate->rooms->first();

    $this->actingAs($account->owner)
        ->post(route('estimates.interview.answer', $estimate), [
            'question_key' => 'material',
            'room_id' => $room->id,
            'value' => 'lvp',
        ])
        ->assertRedirect();

    expect(EstimateLineItem::where('estimate_id', $estimate->id)->count())->toBe(0);
});

test('line items are served in the edit page payload', function () {
    $account = Account::factory()->create();
    $estimate = lineItemEstimate($account);

    answerAll($this, $estimate, $account);

    $this->actingAs($account->owner)
        ->get(route('estimates.edit', $estimate))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('estimates/edit')
            ->has('line_items')
            ->where('line_items.0.key', 'remove_carpet')
        );
});

test('unit price can be set on a line item', function () {
    $account = Account::factory()->create();
    $estimate = lineItemEstimate($account);

    answerAll($this, $estimate, $account);

    $lineItem = EstimateLineItem::where('estimate_id', $estimate->id)->first();
    expect($lineItem->unit_price)->toBeNull();

    $this->actingAs($account->owner)
        ->patch(route('estimates.line-items.update', [$estimate, $lineItem]), [
            'unit_price' => 3.50,
        ])
        ->assertRedirect();

    $lineItem->refresh();
    expect($lineItem->unit_price)->toBe('3.50');
});

test('unit price can be cleared', function () {
    $account = Account::factory()->create();
    $estimate = lineItemEstimate($account);

    answerAll($this, $estimate, $account);

    $lineItem = EstimateLineItem::where('estimate_id', $estimate->id)->first();
    $lineItem->update(['unit_price' => 5.00]);

    $this->actingAs($account->owner)
        ->patch(route('estimates.line-items.update', [$estimate, $lineItem]), [
            'unit_price' => null,
        ])
        ->assertRedirect();

    $lineItem->refresh();
    expect($lineItem->unit_price)->toBeNull();
});

test('another account cannot update line items', function () {
    $account = Account::factory()->create();
    $estimate = lineItemEstimate($account);

    answerAll($this, $estimate, $account);

    $lineItem = EstimateLineItem::where('estimate_id', $estimate->id)->first();
    $intruder = Account::factory()->create();

    $this->actingAs($intruder->owner)
        ->patch(route('estimates.line-items.update', [$estimate, $lineItem]), [
            'unit_price' => 99.99,
        ])
        ->assertNotFound();
});

test('unit price rejects negative values', function () {
    $account = Account::factory()->create();
    $estimate = lineItemEstimate($account);

    answerAll($this, $estimate, $account);

    $lineItem = EstimateLineItem::where('estimate_id', $estimate->id)->first();

    $this->actingAs($account->owner)
        ->patch(route('estimates.line-items.update', [$estimate, $lineItem]), [
            'unit_price' => -1.00,
        ])
        ->assertSessionHasErrors('unit_price');
});

test('unit price rejects non-numeric values', function () {
    $account = Account::factory()->create();
    $estimate = lineItemEstimate($account);

    answerAll($this, $estimate, $account);

    $lineItem = EstimateLineItem::where('estimate_id', $estimate->id)->first();

    $this->actingAs($account->owner)
        ->patch(route('estimates.line-items.update', [$estimate, $lineItem]), [
            'unit_price' => 'abc',
        ])
        ->assertSessionHasErrors('unit_price');
});

test('unit price rejects values over the max', function () {
    $account = Account::factory()->create();
    $estimate = lineItemEstimate($account);

    answerAll($this, $estimate, $account);

    $lineItem = EstimateLineItem::where('estimate_id', $estimate->id)->first();

    $this->actingAs($account->owner)
        ->patch(route('estimates.line-items.update', [$estimate, $lineItem]), [
            'unit_price' => 100000000.00,
        ])
        ->assertSessionHasErrors('unit_price');
});

test('notes can be set on a line item', function () {
    $account = Account::factory()->create();
    $estimate = lineItemEstimate($account);

    answerAll($this, $estimate, $account);

    $lineItem = EstimateLineItem::where('estimate_id', $estimate->id)->first();

    $this->actingAs($account->owner)
        ->patch(route('estimates.line-items.update', [$estimate, $lineItem]), [
            'notes' => 'Mohawk SolidTech 20mil, graphite',
        ])
        ->assertRedirect();

    $lineItem->refresh();
    expect($lineItem->notes)->toBe('Mohawk SolidTech 20mil, graphite');
});

test('deleting an estimate removes its line items', function () {
    $account = Account::factory()->create();
    $estimate = lineItemEstimate($account);

    answerAll($this, $estimate, $account);
    $estimateId = $estimate->id;
    expect(EstimateLineItem::where('estimate_id', $estimateId)->count())->toBeGreaterThan(0);

    $this->actingAs($account->owner)
        ->delete(route('estimates.destroy', $estimate))
        ->assertRedirect();

    expect(EstimateLineItem::where('estimate_id', $estimateId)->count())->toBe(0);
});
