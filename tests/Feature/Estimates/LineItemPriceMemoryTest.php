<?php

use App\Enums\EstimateStatus;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\EstimateLineItem;
use App\Models\EstimateRoom;
use Illuminate\Support\Facades\DB;

function priceMemoryEstimate(Account $account): Estimate
{
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

function completeFlooringInterview(object $testCase, Estimate $estimate, Account $account): void
{
    $room = $estimate->rooms->first();
    $user = $account->owner;

    $answers = [
        ['material', 'lvp', $room->id],
        ['existing', 'carpet', $room->id],
        ['subfloor', 'none', $room->id],
        ['furniture', 'empty', $room->id],
        ['customer_type', 'person', null],
        ['demo_haul_away', 'van', null],
        ['baseboards', 'leave', null],
        ['quarter_round', 'none', null],
        ['transitions', 0, null],
        ['door_undercuts', 0, null],
        ['toilet_pulls', 0, null],
    ];

    foreach ($answers as [$key, $value, $roomId]) {
        $testCase->actingAs($user)
            ->post(route('estimates.interview.answer', $estimate), [
                'question_key' => $key,
                'room_id' => $roomId,
                'value' => $value,
            ])
            ->assertRedirect();
    }
}

test('a prior labor price in the account pre-fills a new estimate and marks it reused', function () {
    $account = Account::factory()->create();

    $first = priceMemoryEstimate($account);
    completeFlooringInterview($this, $first, $account);

    // Contractor prices labor on the first estimate.
    EstimateLineItem::where('estimate_id', $first->id)
        ->where('key', 'install_lvp_labor')
        ->firstOrFail()
        ->update(['unit_price' => 3.25]);

    // A brand-new estimate in the same account, same work.
    $second = priceMemoryEstimate($account);
    completeFlooringInterview($this, $second, $account);

    $laborItem = EstimateLineItem::where('estimate_id', $second->id)
        ->where('key', 'install_lvp_labor')
        ->firstOrFail();

    expect($laborItem->unit_price)->toBe('3.25')
        ->and($laborItem->price_prefilled)->toBeTrue();
});

test('material prices are never pre-filled', function () {
    $account = Account::factory()->create();

    $first = priceMemoryEstimate($account);
    completeFlooringInterview($this, $first, $account);

    EstimateLineItem::where('estimate_id', $first->id)
        ->where('key', 'install_lvp_material')
        ->firstOrFail()
        ->update(['unit_price' => 5.00]);

    $second = priceMemoryEstimate($account);
    completeFlooringInterview($this, $second, $account);

    $materialItem = EstimateLineItem::where('estimate_id', $second->id)
        ->where('key', 'install_lvp_material')
        ->firstOrFail();

    expect($materialItem->unit_price)->toBeNull()
        ->and($materialItem->price_prefilled)->toBeFalse();
});

test('a labor key with no prior price stays blank', function () {
    $account = Account::factory()->create();

    $first = priceMemoryEstimate($account);
    completeFlooringInterview($this, $first, $account);
    // Nothing priced on the first estimate.

    $second = priceMemoryEstimate($account);
    completeFlooringInterview($this, $second, $account);

    $laborItem = EstimateLineItem::where('estimate_id', $second->id)
        ->where('key', 'install_lvp_labor')
        ->firstOrFail();

    expect($laborItem->unit_price)->toBeNull()
        ->and($laborItem->price_prefilled)->toBeFalse();
});

test('prices do not leak across accounts', function () {
    $accountA = Account::factory()->create();
    $accountB = Account::factory()->create();

    // Account B prices the same key.
    $bEstimate = priceMemoryEstimate($accountB);
    completeFlooringInterview($this, $bEstimate, $accountB);
    EstimateLineItem::where('estimate_id', $bEstimate->id)
        ->where('key', 'install_lvp_labor')
        ->firstOrFail()
        ->update(['unit_price' => 9.99]);

    // Account A has never priced anything.
    $aEstimate = priceMemoryEstimate($accountA);
    completeFlooringInterview($this, $aEstimate, $accountA);

    $laborItem = EstimateLineItem::where('estimate_id', $aEstimate->id)
        ->where('key', 'install_lvp_labor')
        ->firstOrFail();

    expect($laborItem->unit_price)->toBeNull()
        ->and($laborItem->price_prefilled)->toBeFalse();
});

test('editing a pre-filled price clears the reused flag', function () {
    $account = Account::factory()->create();

    $first = priceMemoryEstimate($account);
    completeFlooringInterview($this, $first, $account);
    EstimateLineItem::where('estimate_id', $first->id)
        ->where('key', 'install_lvp_labor')
        ->firstOrFail()
        ->update(['unit_price' => 3.25]);

    $second = priceMemoryEstimate($account);
    completeFlooringInterview($this, $second, $account);

    $laborItem = EstimateLineItem::where('estimate_id', $second->id)
        ->where('key', 'install_lvp_labor')
        ->firstOrFail();
    expect($laborItem->price_prefilled)->toBeTrue();

    $this->actingAs($account->owner)
        ->patch(route('estimates.line-items.update', [$second, $laborItem]), [
            'unit_price' => 4.00,
        ])
        ->assertRedirect();

    $laborItem->refresh();
    expect($laborItem->unit_price)->toBe('4.00')
        ->and($laborItem->price_prefilled)->toBeFalse();
});

test('the most recent prior labor price wins', function () {
    $account = Account::factory()->create();

    $first = priceMemoryEstimate($account);
    completeFlooringInterview($this, $first, $account);
    EstimateLineItem::where('estimate_id', $first->id)
        ->where('key', 'install_lvp_labor')
        ->firstOrFail()
        ->update(['unit_price' => 2.00]);

    $second = priceMemoryEstimate($account);
    completeFlooringInterview($this, $second, $account);
    EstimateLineItem::where('estimate_id', $second->id)
        ->where('key', 'install_lvp_labor')
        ->firstOrFail()
        ->update(['unit_price' => 6.50, 'price_prefilled' => false]);

    $third = priceMemoryEstimate($account);
    completeFlooringInterview($this, $third, $account);

    $laborItem = EstimateLineItem::where('estimate_id', $third->id)
        ->where('key', 'install_lvp_labor')
        ->firstOrFail();

    expect($laborItem->unit_price)->toBe('6.50');
});

test('a deprecated line item is not used as a price source', function () {
    $account = Account::factory()->create();

    // A real, reviewed price on a live quote.
    $first = priceMemoryEstimate($account);
    completeFlooringInterview($this, $first, $account);
    EstimateLineItem::where('estimate_id', $first->id)
        ->where('key', 'install_lvp_labor')
        ->firstOrFail()
        ->update(['unit_price' => 3.00, 'price_prefilled' => false]);

    // A fat-fingered price that is then dropped from its quote: switching the
    // material re-emits against tile and deprecates the LVP rows. The bad
    // number is now the newest row for that key, but it never reached a
    // customer and was never corrected.
    $second = priceMemoryEstimate($account);
    completeFlooringInterview($this, $second, $account);
    EstimateLineItem::where('estimate_id', $second->id)
        ->where('key', 'install_lvp_labor')
        ->firstOrFail()
        ->update(['unit_price' => 300.00, 'price_prefilled' => false]);

    $this->actingAs($account->owner)
        ->post(route('estimates.interview.answer', $second), [
            'question_key' => 'material',
            'room_id' => $second->rooms->first()->id,
            'value' => 'tile',
        ])
        ->assertRedirect();

    expect(
        EstimateLineItem::where('estimate_id', $second->id)
            ->where('key', 'install_lvp_labor')
            ->firstOrFail()
            ->deprecated_at
    )->not->toBeNull();

    $third = priceMemoryEstimate($account);
    completeFlooringInterview($this, $third, $account);

    expect(
        EstimateLineItem::where('estimate_id', $third->id)
            ->where('key', 'install_lvp_labor')
            ->firstOrFail()
            ->unit_price
    )->toBe('3.00');
});

test('re-answering an already-emitted interview runs no price lookup', function () {
    $account = Account::factory()->create();

    $estimate = priceMemoryEstimate($account);
    completeFlooringInterview($this, $estimate, $account);

    // Answering transitions for the first time emits a new key, so that edit
    // legitimately needs a lookup. Bumping the count afterwards does not.
    $this->actingAs($account->owner)
        ->post(route('estimates.interview.answer', $estimate), [
            'question_key' => 'transitions',
            'room_id' => null,
            'value' => 2,
        ])
        ->assertRedirect();

    // Steady state: every emitted key already has a row, so no draft is
    // eligible for a prefill and the cross-estimate lookup should not run.
    $lookups = 0;
    DB::listen(function ($query) use (&$lookups): void {
        if (str_contains($query->sql, 'max(estimate_line_items.id)')) {
            $lookups++;
        }
    });

    $this->actingAs($account->owner)
        ->post(route('estimates.interview.answer', $estimate), [
            'question_key' => 'transitions',
            'room_id' => null,
            'value' => 3,
        ])
        ->assertRedirect();

    expect($lookups)->toBe(0);
});
