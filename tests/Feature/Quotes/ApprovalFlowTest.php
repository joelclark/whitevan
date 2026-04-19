<?php

use App\Models\Account;
use App\Models\ContractTemplate;
use App\Models\Customer;
use App\Models\Estimate;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    ContractTemplate::factory()->create(['kind' => ContractTemplate::DEFAULT_KIND]);
});

function approvalUrl(Estimate $estimate): string
{
    return route('approve.show', ['approval_token' => $estimate->approval_token]);
}

test('tracker-appended query parameters still resolve the approval page', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->quoteSent()->create();

    // Simulates Gmail click-wrap, Outlook Safe Links, or marketing automation
    // appending tracking parameters between the customer's inbox and browser.
    $trackedUrl = approvalUrl($estimate).'?utm_source=email&utm_campaign=quote&tracking_id=abc123';

    $this->get($trackedUrl)->assertOk();
});

test('returns 404 when quote is not sent', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    Estimate::factory()->forCustomer($customer)->create([
        'approval_token' => 'some-approval-ulid',
    ]);

    $this->get(route('approve.show', ['approval_token' => 'some-approval-ulid']))
        ->assertNotFound();
});

test('overview renders three steps and omits the contract body', function () {
    $account = Account::factory()->create(['name' => 'Acme Floors']);
    $customer = Customer::factory()->create([
        'account_id' => $account->id,
        'first_name' => 'Dana',
    ]);
    $estimate = Estimate::factory()->forCustomer($customer)->quoteSent()->create([
        'title' => 'Kitchen + Hall',
    ]);

    $this->get(approvalUrl($estimate))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('quotes/approve')
            ->where('estimate.title', 'Kitchen + Hall')
            ->where('customer.first_name', 'Dana')
            ->where('account_name', 'Acme Floors')
            ->has('steps', 3)
            ->where('steps.0.state', 'pending')
            ->where('steps.1.state', 'coming_soon')
            ->where('steps.2.state', 'coming_soon')
            ->where('contract.signed', null)
            ->missing('contract.body_html')
        );
});

test('overview marks step 1 complete and surfaces the signed banner', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->quoteSent()->create();
    $estimate->forceFill([
        'contract_body_snapshot' => '# signed',
        'contract_signed_at' => now(),
        'contract_signed_name' => 'Dana',
    ])->save();

    $this->get(approvalUrl($estimate))
        ->assertInertia(fn (Assert $page) => $page
            ->component('quotes/approve')
            ->where('steps.0.state', 'complete')
            ->where('contract.signed.name', 'Dana')
        );
});
