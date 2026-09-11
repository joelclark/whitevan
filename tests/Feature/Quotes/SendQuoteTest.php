<?php

use App\Enums\ActivityEvent;
use App\Enums\QuoteStatus;
use App\Mail\QuoteSentToCustomer;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\EstimateLineItem;
use Illuminate\Support\Facades\Mail;

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
    expect($estimate)->toHaveRecordedProjectEvent(ActivityEvent::EstimateQuoteSent);
});

test('approval_token is generated on first send', function () {
    Mail::fake();

    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create();
    EstimateLineItem::factory()->create([
        'estimate_id' => $estimate->id,
        'unit_price' => 5.50,
    ]);

    $this->actingAs($account->owner)
        ->post(route('estimates.send-quote', $estimate));

    expect($estimate->refresh()->approval_token)->not->toBeNull();
});

test('re-send reuses the existing approval_token', function () {
    Mail::fake();

    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->quoteSent()->create([
        'approval_token' => 'existing-approval-token-ulid',
    ]);
    EstimateLineItem::factory()->create([
        'estimate_id' => $estimate->id,
        'unit_price' => 5.50,
    ]);

    $this->actingAs($account->owner)
        ->post(route('estimates.send-quote', $estimate));

    expect($estimate->refresh()->approval_token)->toBe('existing-approval-token-ulid');
});

test('customer email with quote is queued on send', function () {
    Mail::fake();

    $account = Account::factory()->create();
    $customer = Customer::factory()->create([
        'account_id' => $account->id,
        'email' => 'client@example.com',
    ]);
    $estimate = Estimate::factory()->forCustomer($customer)->create();
    EstimateLineItem::factory()->create([
        'estimate_id' => $estimate->id,
        'unit_price' => 5.50,
    ]);

    $this->actingAs($account->owner)
        ->post(route('estimates.send-quote', $estimate))
        ->assertSessionHas('status', 'quote-sent');

    Mail::assertQueued(
        QuoteSentToCustomer::class,
        fn (QuoteSentToCustomer $mail) => $mail->hasTo('client@example.com')
            && $mail->estimate->is($estimate),
    );
});

test('cannot send when estimate is locked', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->quoteSent()->create([
        'locked_at' => now(),
    ]);
    EstimateLineItem::factory()->create([
        'estimate_id' => $estimate->id,
        'unit_price' => 5.50,
    ]);

    $this->actingAs($account->owner)
        ->post(route('estimates.send-quote', $estimate))
        ->assertStatus(409);
});

test('invitation email body contains no totals and only the approval link', function () {
    $account = Account::factory()->create(['name' => 'Acme Floors']);
    $customer = Customer::factory()->create([
        'account_id' => $account->id,
        'first_name' => 'Pat',
        'email' => 'pat@example.com',
    ]);
    $estimate = Estimate::factory()->forCustomer($customer)->create([
        'title' => 'Upstairs hallway',
        'total_sqft' => 420,
    ]);
    EstimateLineItem::factory()->create([
        'estimate_id' => $estimate->id,
        'unit_price' => 5.50,
    ]);

    $this->actingAs($account->owner)
        ->post(route('estimates.send-quote', $estimate));

    $estimate->refresh();
    $mail = new QuoteSentToCustomer($estimate);
    $rendered = $mail->render();

    expect($rendered)->toContain('Your quote is ready');
    expect($rendered)->toContain('Upstairs hallway');
    expect($rendered)->toContain('View and accept your quote');
    expect($rendered)->toContain($estimate->approval_token);
    expect($rendered)->not->toContain('$'); // no dollar amounts in the invitation
});

test('no mail is queued when customer has no email', function () {
    Mail::fake();

    $account = Account::factory()->create();
    $customer = Customer::factory()->create([
        'account_id' => $account->id,
        'email' => null,
    ]);
    $estimate = Estimate::factory()->forCustomer($customer)->create();
    EstimateLineItem::factory()->create([
        'estimate_id' => $estimate->id,
        'unit_price' => 5.50,
    ]);

    $this->actingAs($account->owner)
        ->post(route('estimates.send-quote', $estimate))
        ->assertSessionHas('status', 'quote-saved-no-email');

    Mail::assertNothingQueued();

    // Quote itself still transitioned and approval_token was still generated.
    $estimate->refresh();
    expect($estimate->quote_status)->toBe(QuoteStatus::Sent)
        ->and($estimate->approval_token)->not->toBeNull();
});

test('cannot send while a reused price is still unconfirmed', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create();
    EstimateLineItem::factory()->create([
        'estimate_id' => $estimate->id,
        'unit_price' => 12.00,
        'price_prefilled' => true,
    ]);

    $this->actingAs($account->owner)
        ->post(route('estimates.send-quote', $estimate))
        ->assertStatus(422);

    expect($estimate->refresh()->quote_status)->toBeNull();
});

test('confirming a reused price unblocks the send', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create();
    $item = EstimateLineItem::factory()->create([
        'estimate_id' => $estimate->id,
        'unit_price' => 12.00,
        'price_prefilled' => true,
    ]);

    // Re-submitting the same price is how the contractor takes ownership of it.
    $this->actingAs($account->owner)
        ->patch(route('estimates.line-items.update', [$estimate, $item]), [
            'unit_price' => 12.00,
        ])
        ->assertRedirect();

    expect($item->refresh()->price_prefilled)->toBeFalse();

    $this->actingAs($account->owner)
        ->post(route('estimates.send-quote', $estimate))
        ->assertRedirect();

    expect($estimate->refresh()->quote_status)->toBe(QuoteStatus::Sent);
});
