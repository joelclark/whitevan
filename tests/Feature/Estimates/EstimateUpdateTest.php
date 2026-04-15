<?php

use App\Enums\ActivityEvent;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Estimate;

test('members can rename an estimate', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create([
        'title' => 'Old title',
    ]);

    $this->actingAs($user)
        ->patch(route('estimates.update', $estimate), [
            'title' => 'New title',
        ])
        ->assertRedirect();

    expect($estimate->refresh()->title)->toBe('New title');
    expect(ActivityLog::where('event', ActivityEvent::EstimateUpdated)->count())->toBe(1);
});

test('interview answers and line item prices round-trip as a map', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create();

    $this->actingAs($user)
        ->patch(route('estimates.update', $estimate), [
            'interview_answers' => ['carpet' => 'yes', 'paint' => 'no'],
            'line_item_prices' => ['carpet' => '2500', 'paint' => '0'],
        ])
        ->assertRedirect();

    $estimate->refresh();
    expect($estimate->interview_answers['carpet'])->toBe('yes');
    expect($estimate->line_item_prices['carpet'])->toBe('2500');
});

test('a member in another account cannot update the estimate', function () {
    $otherAccount = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $otherAccount->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create();

    $account = Account::factory()->create();

    $this->actingAs($account->owner)
        ->patch(route('estimates.update', $estimate), ['title' => 'Leak'])
        ->assertNotFound();

    expect($estimate->refresh()->title)->not->toBe('Leak');
});
