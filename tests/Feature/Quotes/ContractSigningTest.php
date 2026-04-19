<?php

use App\Enums\ActivityEvent;
use App\Enums\ActorType;
use App\Models\Account;
use App\Models\AccountContractOverride;
use App\Models\ContractTemplate;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\ProjectEvent;
use App\Services\ProjectEventLogger;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    ContractTemplate::factory()->create([
        'kind' => ContractTemplate::DEFAULT_KIND,
        'body' => '# Default contract body for tests',
    ]);
});

function makeSentEstimate(): Estimate
{
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);

    return Estimate::factory()->forCustomer($customer)->quoteSent()->create();
}

test('sign page renders the effective contract body as HTML', function () {
    $estimate = makeSentEstimate();

    $this->get(route('approve.sign.show', ['approval_token' => $estimate->approval_token]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('quotes/sign')
            ->has('contract.body_html')
            ->where('contract.signed', null)
        );
});

test('sign page uses the account override when one exists', function () {
    $estimate = makeSentEstimate();
    AccountContractOverride::factory()->create([
        'account_id' => $estimate->account_id,
        'body' => 'Custom override text visible to customer',
    ]);

    $this->get(route('approve.sign.show', ['approval_token' => $estimate->approval_token]))
        ->assertInertia(fn ($page) => $page
            ->component('quotes/sign')
            ->where(
                'contract.body_html',
                fn (string $html) => str_contains($html, 'Custom override text visible to customer'),
            )
        );
});

test('first contract view records a customer-visible project event exactly once', function () {
    $estimate = makeSentEstimate();

    // Overview visits do not fire the contract-view event anymore.
    $this->get(route('approve.show', ['approval_token' => $estimate->approval_token]));
    expect(ProjectEvent::withoutGlobalScopes()
        ->where('estimate_id', $estimate->id)
        ->where('event', ActivityEvent::QuoteContractViewed)
        ->count())->toBe(0);

    $this->get(route('approve.sign.show', ['approval_token' => $estimate->approval_token]));
    $this->get(route('approve.sign.show', ['approval_token' => $estimate->approval_token]));

    $events = ProjectEvent::withoutGlobalScopes()
        ->where('estimate_id', $estimate->id)
        ->where('event', ActivityEvent::QuoteContractViewed)
        ->get();

    expect($events)->toHaveCount(1);
    expect($events->first()->actor_type)->toBe(ActorType::Customer);
    expect($events->first()->customer_visible)->toBeTrue();
});

test('customer can sign the contract', function () {
    $estimate = makeSentEstimate();

    $this->post(
        route('approve.sign', ['approval_token' => $estimate->approval_token]),
        ['name' => 'Dana Customer', 'acknowledged' => '1'],
    )->assertRedirect(route('approve.show', ['approval_token' => $estimate->approval_token]));

    $estimate->refresh();
    expect($estimate->contract_signed_at)->not->toBeNull();
    expect($estimate->contract_signed_name)->toBe('Dana Customer');
    expect($estimate->contract_signed_ip)->not->toBeNull();
    expect($estimate->contract_body_snapshot)->toContain('Default contract body');

    $events = ProjectEvent::withoutGlobalScopes()
        ->where('estimate_id', $estimate->id)
        ->where('event', ActivityEvent::QuoteContractSigned)
        ->get();
    expect($events)->toHaveCount(1);
    expect($events->first()->actor_type)->toBe(ActorType::Customer);
    expect($events->first()->metadata['name'] ?? null)->toBe('Dana Customer');
});

test('signing requires the acknowledged box to be checked', function () {
    $estimate = makeSentEstimate();

    $this->post(
        route('approve.sign', ['approval_token' => $estimate->approval_token]),
        ['name' => 'Dana Customer'],
    )->assertInvalid(['acknowledged']);

    expect($estimate->refresh()->contract_signed_at)->toBeNull();
});

test('signing requires a name of at least 2 characters', function () {
    $estimate = makeSentEstimate();

    $this->post(
        route('approve.sign', ['approval_token' => $estimate->approval_token]),
        ['name' => 'A', 'acknowledged' => '1'],
    )->assertInvalid(['name']);
});

test('already-signed estimate cannot be signed again', function () {
    $estimate = makeSentEstimate();

    $this->post(
        route('approve.sign', ['approval_token' => $estimate->approval_token]),
        ['name' => 'Dana Customer', 'acknowledged' => '1'],
    )->assertRedirect();

    $firstSignedAt = $estimate->refresh()->contract_signed_at;

    $this->post(
        route('approve.sign', ['approval_token' => $estimate->approval_token]),
        ['name' => 'Imposter', 'acknowledged' => '1'],
    )->assertNotFound();

    $estimate->refresh();
    expect($estimate->contract_signed_name)->toBe('Dana Customer');
    expect($estimate->contract_signed_at->equalTo($firstSignedAt))->toBeTrue();
});

test('sign page shows the frozen snapshot after signing, not the current body', function () {
    $estimate = makeSentEstimate();

    $this->post(
        route('approve.sign', ['approval_token' => $estimate->approval_token]),
        ['name' => 'Dana', 'acknowledged' => '1'],
    );

    // Override the account contract AFTER signing.
    AccountContractOverride::factory()->create([
        'account_id' => $estimate->account_id,
        'body' => 'Drastically different post-sign terms that should NOT appear',
    ]);

    $this->get(route('approve.sign.show', ['approval_token' => $estimate->approval_token]))
        ->assertInertia(fn ($page) => $page
            ->component('quotes/sign')
            ->where('contract.signed.name', 'Dana')
            ->where(
                'contract.body_html',
                fn (string $html) => str_contains($html, 'Default contract body')
                    && ! str_contains($html, 'Drastically different'),
            )
        );
});

test('contract-sign write is an atomic conditional update', function () {
    // Pins the controller's race-safety guarantee: even if two concurrent
    // POSTs both pass the in-memory `hasSignedContract()` fast-path, only
    // one UPDATE can claim the row because the `whereNull` predicate fails
    // for every subsequent writer.
    $estimate = makeSentEstimate();

    $attempt = fn (string $name) => Estimate::withoutGlobalScope('account')
        ->whereKey($estimate->id)
        ->whereNull('contract_signed_at')
        ->update([
            'contract_body_snapshot' => 'snapshot',
            'contract_signed_at' => now(),
            'contract_signed_name' => $name,
            'contract_signed_ip' => '1.1.1.1',
            'contract_signed_user_agent' => 'agent',
        ]);

    expect($attempt('First'))->toBe(1);
    expect($attempt('Second'))->toBe(0);
    expect($estimate->refresh()->contract_signed_name)->toBe('First');
});

test('a duplicate QuoteContractViewed event is rejected by the database', function () {
    // Pins the partial unique index `project_events_contract_viewed_unique`
    // — the DB-level guarantee behind `recordFirstViewIfNew`. Two concurrent
    // GETs can both pass the controller's exists() fast-path, but only one
    // insert survives.
    $estimate = makeSentEstimate();

    ProjectEventLogger::record(
        $estimate,
        ActivityEvent::QuoteContractViewed,
        actorType: ActorType::Customer,
    );

    expect(fn () => ProjectEventLogger::record(
        $estimate,
        ActivityEvent::QuoteContractViewed,
        actorType: ActorType::Customer,
    ))->toThrow(UniqueConstraintViolationException::class);
});

test('visiting the sign page after signing does not record a new view event', function () {
    $estimate = makeSentEstimate();

    $this->post(
        route('approve.sign', ['approval_token' => $estimate->approval_token]),
        ['name' => 'Dana', 'acknowledged' => '1'],
    );

    // Sign action records ContractSigned, and the first sign page visit before
    // signing may or may not have recorded ContractViewed. Baseline it here.
    $viewBefore = ProjectEvent::withoutGlobalScopes()
        ->where('estimate_id', $estimate->id)
        ->where('event', ActivityEvent::QuoteContractViewed)
        ->count();

    $this->get(route('approve.sign.show', ['approval_token' => $estimate->approval_token]))
        ->assertOk();

    $viewAfter = ProjectEvent::withoutGlobalScopes()
        ->where('estimate_id', $estimate->id)
        ->where('event', ActivityEvent::QuoteContractViewed)
        ->count();

    expect($viewAfter)->toBe($viewBefore);
});
