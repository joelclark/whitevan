<?php

use App\Enums\ActivityEvent;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\EstimateFloorplanPage;
use App\Models\EstimateLineItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('valid token returns the quote page', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->quoteSent()->create();
    EstimateLineItem::factory()->create([
        'estimate_id' => $estimate->id,
        'unit_price' => 10.00,
    ]);

    $this->get(route('quotes.show', $estimate->quote_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('quotes/show')
            ->has('estimate')
            ->has('customer')
            ->has('line_items')
            ->has('account_name')
        );
});

test('returns 404 for nonexistent token', function () {
    $this->get(route('quotes.show', 'nonexistent-token'))
        ->assertNotFound();
});

test('returns 404 for estimate with null quote_status', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    Estimate::factory()->forCustomer($customer)->create([
        'quote_token' => 'some-token',
    ]);

    $this->get(route('quotes.show', 'some-token'))
        ->assertNotFound();
});

test('returns 404 for token with non-sent quote_status', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->quoteSent()->create();

    // Simulate a future enum value by writing directly to the DB
    DB::table('estimates')
        ->where('id', $estimate->id)
        ->update(['quote_status' => 'accepted']);

    $this->get(route('quotes.show', $estimate->quote_token))
        ->assertNotFound();
});

test('updates quote_customer_viewed_at on view', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->quoteSent()->create();

    expect($estimate->quote_customer_viewed_at)->toBeNull();

    $this->get(route('quotes.show', $estimate->quote_token));

    expect($estimate->refresh()->quote_customer_viewed_at)->not->toBeNull();
});

test('has_changed is true on first view', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->quoteSent()->create();

    $this->get(route('quotes.show', $estimate->quote_token))
        ->assertInertia(fn (Assert $page) => $page
            ->where('has_changed', true)
        );
});

test('has_changed is false on immediate re-view', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->quoteSent()->create();

    // First view sets quote_customer_viewed_at
    $this->get(route('quotes.show', $estimate->quote_token))
        ->assertOk();

    // Second view should not show as changed
    $response = $this->get(route('quotes.show', $estimate->quote_token));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('has_changed', false)
    );
});

test('has_changed is true after line item price update', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->quoteSent()->create();
    $lineItem = EstimateLineItem::factory()->create([
        'estimate_id' => $estimate->id,
        'unit_price' => 5.00,
    ]);

    // Customer views
    $this->get(route('quotes.show', $estimate->quote_token));

    // Price is updated after the customer viewed
    $this->travel(1)->minutes();
    $lineItem->update(['unit_price' => 8.00]);

    $this->get(route('quotes.show', $estimate->quote_token))
        ->assertInertia(fn (Assert $page) => $page
            ->where('has_changed', true)
        );
});

test('has_changed is true after a new line item is added', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->quoteSent()->create();
    EstimateLineItem::factory()->create([
        'estimate_id' => $estimate->id,
        'unit_price' => 5.00,
    ]);

    // Customer views
    $this->get(route('quotes.show', $estimate->quote_token));

    // New line item added after customer viewed
    $this->travel(1)->minutes();
    EstimateLineItem::factory()->create([
        'estimate_id' => $estimate->id,
        'unit_price' => 12.00,
    ]);

    $this->get(route('quotes.show', $estimate->quote_token))
        ->assertInertia(fn (Assert $page) => $page
            ->where('has_changed', true)
        );
});

test('has_changed is true after a line item is deprecated', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->quoteSent()->create();
    $lineItem = EstimateLineItem::factory()->create([
        'estimate_id' => $estimate->id,
        'unit_price' => 5.00,
    ]);
    EstimateLineItem::factory()->create([
        'estimate_id' => $estimate->id,
        'unit_price' => 10.00,
    ]);

    // Customer views
    $this->get(route('quotes.show', $estimate->quote_token));

    // Line item deprecated after customer viewed — this won't show up in
    // activeLineItems, so latestContentChange uses the remaining active item.
    // But the estimate itself is touched when we forceFill deprecated_at.
    $this->travel(1)->minutes();
    $estimate->touch();

    $this->get(route('quotes.show', $estimate->quote_token))
        ->assertInertia(fn (Assert $page) => $page
            ->where('has_changed', true)
        );
});

test('no auth required', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->quoteSent()->create();

    // No actingAs — fully unauthenticated
    $this->get(route('quotes.show', $estimate->quote_token))
        ->assertOk();
});

test('fires activity event on guest view attributed to the correct account', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->quoteSent()->create();

    $this->get(route('quotes.show', $estimate->quote_token));

    $log = ActivityLog::where('event', ActivityEvent::EstimateQuoteViewed)->sole();
    expect($log->account_id)->toBe($account->id)
        ->and($log->user_id)->toBeNull();
});

test('authenticated user view does not update quote_customer_viewed_at', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->quoteSent()->create();

    $project = $estimate->project;
    $project->forceFill(['last_activity_at' => now()->subDays(3)])->save();
    $before = $project->fresh()->last_activity_at;

    $this->actingAs($account->owner)
        ->get(route('quotes.show', $estimate->quote_token))
        ->assertOk();

    expect($estimate->refresh()->quote_customer_viewed_at)->toBeNull();
    expect(ActivityLog::where('event', ActivityEvent::EstimateQuoteViewed)->count())->toBe(0);
    expect($project->fresh()->last_activity_at->equalTo($before))->toBeTrue();
});

test('floorplan page endpoint serves png for valid token', function () {
    Storage::fake('local');

    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->quoteSent()->create();

    $imagePath = "estimate-floorplan-pages/{$estimate->id}/p1.png";
    Storage::disk('local')->put($imagePath, 'fake-png-data');

    EstimateFloorplanPage::factory()->create([
        'estimate_id' => $estimate->id,
        'page' => 1,
        'image_path' => $imagePath,
    ]);

    $this->get(route('quotes.floorplan-page', [
        'token' => $estimate->quote_token,
        'page' => 1,
    ]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

test('floorplan page endpoint returns 404 for invalid token', function () {
    $this->get(route('quotes.floorplan-page', [
        'token' => 'bad-token',
        'page' => 1,
    ]))
        ->assertNotFound();
});

test('floorplan page endpoint returns 404 for nonexistent page row', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->quoteSent()->create();

    // No floorplan page rows exist for this estimate
    $this->get(route('quotes.floorplan-page', [
        'token' => $estimate->quote_token,
        'page' => 99,
    ]))
        ->assertNotFound();
});

test('floorplan page endpoint returns 404 when image file is missing from disk', function () {
    Storage::fake('local');

    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->quoteSent()->create();

    // Row exists but the file was never written / was cleaned up
    EstimateFloorplanPage::factory()->create([
        'estimate_id' => $estimate->id,
        'page' => 1,
        'image_path' => "estimate-floorplan-pages/{$estimate->id}/p1.png",
    ]);

    $this->get(route('quotes.floorplan-page', [
        'token' => $estimate->quote_token,
        'page' => 1,
    ]))
        ->assertNotFound();
});
