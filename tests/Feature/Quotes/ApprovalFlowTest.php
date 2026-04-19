<?php

use App\Models\Account;
use App\Models\Customer;
use App\Models\Estimate;
use Inertia\Testing\AssertableInertia as Assert;

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

test('landing page renders with placeholder steps', function () {
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
            ->has('steps', 3, fn (Assert $step) => $step
                ->has('key')
                ->has('label')
                ->where('state', 'coming_soon')
            )
        );
});
