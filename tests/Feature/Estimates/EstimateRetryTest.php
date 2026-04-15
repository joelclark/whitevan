<?php

use App\Enums\EstimateStatus;
use App\Jobs\ProcessEstimatePdfJob;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Estimate;
use Illuminate\Support\Facades\Queue;

test('a failed estimate can be retried by a member', function () {
    Queue::fake();

    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->failed()->create();

    $this->actingAs($user)
        ->post(route('estimates.retry', $estimate))
        ->assertRedirect();

    $estimate->refresh();
    expect($estimate->status)->toBe(EstimateStatus::Processing);
    expect($estimate->agent_errors)->toBe([]);

    Queue::assertPushed(
        ProcessEstimatePdfJob::class,
        fn (ProcessEstimatePdfJob $job) => $job->estimateId === $estimate->id,
    );
});

test('a ready estimate can be resubmitted', function () {
    Queue::fake();

    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create([
        'status' => EstimateStatus::Ready,
    ]);

    $this->actingAs($user)
        ->post(route('estimates.retry', $estimate))
        ->assertRedirect();

    expect($estimate->refresh()->status)->toBe(EstimateStatus::Processing);

    Queue::assertPushed(
        ProcessEstimatePdfJob::class,
        fn (ProcessEstimatePdfJob $job) => $job->estimateId === $estimate->id,
    );
});

test('retry wipes existing interview answers so room-scoped data does not orphan', function () {
    Queue::fake();

    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create([
        'status' => EstimateStatus::Ready,
        'interview_answers' => [
            'rooms' => ['99' => ['material' => 'lvp']],
            'long_tail' => ['demo_haul_away' => 'van'],
        ],
    ]);

    $this->actingAs($user)
        ->post(route('estimates.retry', $estimate))
        ->assertRedirect();

    $estimate->refresh();
    expect((array) $estimate->interview_answers['rooms'])->toBe([]);
    expect((array) $estimate->interview_answers['long_tail'])->toBe([]);
});

test('an in-flight estimate cannot be resubmitted', function () {
    Queue::fake();

    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->processing()->create();

    $this->actingAs($user)
        ->post(route('estimates.retry', $estimate))
        ->assertStatus(409);

    Queue::assertNothingPushed();
});

test('cross-tenant retry is blocked', function () {
    Queue::fake();

    $otherAccount = Account::factory()->create();
    $otherCustomer = Customer::factory()->create(['account_id' => $otherAccount->id]);
    $estimate = Estimate::factory()->forCustomer($otherCustomer)->failed()->create();

    $account = Account::factory()->create();

    $this->actingAs($account->owner)
        ->post(route('estimates.retry', $estimate))
        ->assertNotFound();

    Queue::assertNothingPushed();
});
