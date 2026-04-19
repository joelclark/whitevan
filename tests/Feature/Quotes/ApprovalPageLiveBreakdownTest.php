<?php

use App\Enums\LineItemCategory;
use App\Enums\LineItemUnit;
use App\Enums\QuoteStatus;
use App\Models\Account;
use App\Models\AccountDepositOverride;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\EstimateLineItem;
use Illuminate\Support\Str;

function approvalEstimate(Account $account): Estimate
{
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create([
        'quote_status' => QuoteStatus::Sent,
        'approval_token' => Str::ulid()->toBase32(),
        'quote_sent_at' => now(),
    ]);

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

test('approval page exposes a live quote snapshot and hash', function () {
    $account = Account::factory()->create();
    $estimate = approvalEstimate($account);

    $this->get(route('approve.show', ['approval_token' => $estimate->approval_token]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('quotes/approve')
            ->where('quote.material_percent', 100)
            ->where('quote.labor_percent', 80)
            ->where('quote.material_subtotal', '427.50')
            ->where('quote.labor_subtotal', '375.00')
            ->where('quote.grand_total', '802.50')
            ->where('quote.material_deposit', '427.50')
            ->where('quote.labor_deposit', '300.00')
            ->where('quote.deposit_total', '727.50')
            ->has('quote_hash')
            ->where('is_locked', false)
        );
});

test('hash reflects admin override changes on the next request', function () {
    $account = Account::factory()->create();
    $estimate = approvalEstimate($account);

    $firstResponse = $this->get(route('approve.show', ['approval_token' => $estimate->approval_token]));
    $firstHash = $firstResponse->viewData('page')['props']['quote_hash'];

    AccountDepositOverride::factory()->create([
        'account_id' => $account->id,
        'material_deposit_percent' => 50,
        'labor_deposit_percent' => 40,
    ]);

    $secondResponse = $this->get(route('approve.show', ['approval_token' => $estimate->approval_token]));
    $secondProps = $secondResponse->viewData('page')['props'];

    expect($secondProps['quote_hash'])->not->toBe($firstHash);
    expect($secondProps['quote']['material_percent'])->toBe(50);
    expect($secondProps['quote']['labor_percent'])->toBe(40);
});

test('approval page 404s on unknown token', function () {
    $this->get(route('approve.show', ['approval_token' => 'not-a-real-token']))
        ->assertNotFound();
});
