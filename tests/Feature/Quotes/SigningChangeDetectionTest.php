<?php

use App\Enums\ActivityEvent;
use App\Enums\LineItemCategory;
use App\Enums\LineItemUnit;
use App\Models\Account;
use App\Models\AccountDepositOverride;
use App\Models\ActivityLog;
use App\Models\ContractTemplate;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\EstimateLineItem;
use App\Services\QuoteSnapshot;

beforeEach(function () {
    ContractTemplate::factory()->create([
        'kind' => ContractTemplate::DEFAULT_KIND,
        'body' => '# Default contract body for tests',
    ]);
});

function changeDetectionEstimate(): Estimate
{
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->quoteSent()->create();

    EstimateLineItem::factory()->for($estimate)->material()->create([
        'key' => 'install_lvp_material',
        'label' => 'LVP — materials',
        'category' => LineItemCategory::Install,
        'unit' => LineItemUnit::Sqft,
        'quantity' => '150',
        'unit_price' => '2.85',
    ]);

    EstimateLineItem::factory()->for($estimate)->labor()->create([
        'key' => 'install_lvp_labor',
        'label' => 'Install LVP — labor',
        'category' => LineItemCategory::Install,
        'unit' => LineItemUnit::Sqft,
        'quantity' => '150',
        'unit_price' => '2.50',
    ]);

    return $estimate;
}

function buildHash(Estimate $estimate): string
{
    $svc = app(QuoteSnapshot::class);

    return $svc->hash($svc->build($estimate->fresh()));
}

test('signing with a matching hash records the signature', function () {
    $estimate = changeDetectionEstimate();
    $hash = buildHash($estimate);

    $this->post(
        route('approve.sign', ['approval_token' => $estimate->approval_token]),
        [
            'name' => 'Pat Signer',
            'acknowledged' => '1',
            'quote_hash' => $hash,
        ],
    )->assertRedirect(route('approve.show', ['approval_token' => $estimate->approval_token]));

    $estimate->refresh();
    expect($estimate->contract_signed_at)->not->toBeNull();

    // Auto-lock on sign is temporarily disabled — locked_at stays null so
    // the contractor can continue editing. When auto-lock is re-enabled,
    // change this to ->not->toBeNull().
    expect($estimate->locked_at)->toBeNull();

    $quoteAcceptedLog = ActivityLog::where('event', ActivityEvent::EstimateQuoteAccepted)->first();
    expect($quoteAcceptedLog)->not->toBeNull();
    expect($quoteAcceptedLog->metadata['grand_total'] ?? null)->toBe('802.50');
});

test('signing with a stale hash is rejected and the estimate is not locked', function () {
    $estimate = changeDetectionEstimate();
    $staleHash = buildHash($estimate);

    // Admin changes deposit override after the customer loaded the page.
    AccountDepositOverride::factory()->create([
        'account_id' => $estimate->account_id,
        'material_deposit_percent' => 50,
        'labor_deposit_percent' => 25,
    ]);

    $this->post(
        route('approve.sign', ['approval_token' => $estimate->approval_token]),
        [
            'name' => 'Pat Signer',
            'acknowledged' => '1',
            'quote_hash' => $staleHash,
        ],
    )
        ->assertRedirect(route('approve.show', ['approval_token' => $estimate->approval_token]))
        ->assertSessionHas('quote_changed', true);

    $estimate->refresh();
    expect($estimate->contract_signed_at)->toBeNull();

    expect(ActivityLog::where('event', ActivityEvent::EstimateQuoteChangeDetectedAtSigning)->count())->toBe(1);
    expect(ActivityLog::where('event', ActivityEvent::EstimateQuoteAccepted)->count())->toBe(0);
});

test('signing with a missing hash fails validation', function () {
    $estimate = changeDetectionEstimate();

    $this->post(
        route('approve.sign', ['approval_token' => $estimate->approval_token]),
        ['name' => 'Pat Signer', 'acknowledged' => '1'],
    )->assertInvalid(['quote_hash']);

    expect($estimate->fresh()->contract_signed_at)->toBeNull();
});

test('signing a locked estimate returns 409', function () {
    $estimate = changeDetectionEstimate();
    $estimate->forceFill(['locked_at' => now()])->save();

    $this->post(
        route('approve.sign', ['approval_token' => $estimate->approval_token]),
        [
            'name' => 'Pat Signer',
            'acknowledged' => '1',
            'quote_hash' => buildHash($estimate),
        ],
    )->assertStatus(409);
});

test('interview updates are blocked after the estimate is locked', function () {
    $estimate = changeDetectionEstimate();
    $user = $estimate->account->owner;
    $estimate->forceFill(['locked_at' => now()])->save();

    // Any valid interview answer — the lock guard should fire before the answer
    // is recorded.
    $this->actingAs($user)
        ->post(route('estimates.interview.answer', $estimate), [
            'question_key' => 'customer_type',
            'value' => 'person',
        ])
        ->assertStatus(409);
});
