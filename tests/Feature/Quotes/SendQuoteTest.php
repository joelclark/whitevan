<?php

use App\Enums\ActivityEvent;
use App\Enums\QuoteStatus;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\EstimateLineItem;

test('guests are redirected to login', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create();

    $this->post(route('estimates.send-quote', $estimate))
        ->assertRedirect(route('login'));
});

test('cannot send when estimate is processing', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->processing()->create();

    $this->actingAs($account->owner)
        ->post(route('estimates.send-quote', $estimate))
        ->assertStatus(422);
});

test('cannot send when estimate is failed', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->failed()->create();

    $this->actingAs($account->owner)
        ->post(route('estimates.send-quote', $estimate))
        ->assertStatus(422);
});

test('cannot send when any active line item has null unit_price', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create();
    EstimateLineItem::factory()->create([
        'estimate_id' => $estimate->id,
        'unit_price' => 10.00,
    ]);
    EstimateLineItem::factory()->create([
        'estimate_id' => $estimate->id,
        'unit_price' => null,
    ]);

    $this->actingAs($account->owner)
        ->post(route('estimates.send-quote', $estimate))
        ->assertStatus(422);
});

test('successful send sets quote_status, generates token, and sets quote_sent_at', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create();
    EstimateLineItem::factory()->create([
        'estimate_id' => $estimate->id,
        'unit_price' => 5.50,
    ]);

    $this->actingAs($account->owner)
        ->post(route('estimates.send-quote', $estimate))
        ->assertRedirect();

    $estimate->refresh();

    expect($estimate->quote_status)->toBe(QuoteStatus::Sent)
        ->and($estimate->quote_token)->not->toBeNull()
        ->and($estimate->quote_sent_at)->not->toBeNull();
});

test('re-send keeps the same token but updates quote_sent_at', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->quoteSent()->create();
    $originalToken = $estimate->quote_token;
    $originalSentAt = $estimate->quote_sent_at;

    EstimateLineItem::factory()->create([
        'estimate_id' => $estimate->id,
        'unit_price' => 5.50,
    ]);

    $this->travel(1)->minutes();

    $this->actingAs($account->owner)
        ->post(route('estimates.send-quote', $estimate))
        ->assertRedirect();

    $estimate->refresh();

    expect($estimate->quote_token)->toBe($originalToken)
        ->and($estimate->quote_sent_at->isAfter($originalSentAt))->toBeTrue();
});

test('another account cannot send someone elses estimate', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create();
    EstimateLineItem::factory()->create([
        'estimate_id' => $estimate->id,
        'unit_price' => 5.50,
    ]);

    $otherAccount = Account::factory()->create();

    $this->actingAs($otherAccount->owner)
        ->post(route('estimates.send-quote', $estimate))
        ->assertNotFound();

    expect($estimate->refresh()->quote_status)->toBeNull();
});

test('activity event is fired on send', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create();
    EstimateLineItem::factory()->create([
        'estimate_id' => $estimate->id,
        'unit_price' => 5.50,
    ]);

    $this->actingAs($account->owner)
        ->post(route('estimates.send-quote', $estimate));

    expect(ActivityLog::where('event', ActivityEvent::EstimateQuoteSent)->count())->toBe(1);
});
