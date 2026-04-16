<?php

use App\Enums\EstimateStatus;
use App\Enums\FloorplanAssetsStatus;
use App\Jobs\ProcessEstimatePdfJob;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Estimate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

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
            'project_wide' => ['demo_haul_away' => 'van'],
        ],
    ]);

    $this->actingAs($user)
        ->post(route('estimates.retry', $estimate))
        ->assertRedirect();

    $estimate->refresh();
    expect((array) $estimate->interview_answers['rooms'])->toBe([]);
    expect((array) $estimate->interview_answers['project_wide'])->toBe([]);
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

test('retry resets floorplan state and removes stale page rows + files', function () {
    Queue::fake();
    Storage::fake('local');

    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create([
        'status' => EstimateStatus::Failed,
        'floorplan_assets_status' => FloorplanAssetsStatus::Failed,
        'debug_log' => [
            'request' => ['kept' => true],
            'floorplan' => ['status' => 'failed', 'errors' => ['boom']],
        ],
    ]);

    Storage::disk('local')->put("estimate-floorplan-pages/{$estimate->id}/p1.png", 'oldcontents');
    $estimate->floorplanPages()->create([
        'page' => 1,
        'image_path' => "estimate-floorplan-pages/{$estimate->id}/p1.png",
        'width' => 100,
        'height' => 100,
    ]);

    $this->actingAs($user)
        ->post(route('estimates.retry', $estimate))
        ->assertRedirect();

    $estimate->refresh();
    expect($estimate->floorplan_assets_status)->toBe(FloorplanAssetsStatus::Pending);
    expect($estimate->floorplanPages)->toHaveCount(0);
    Storage::disk('local')->assertMissing("estimate-floorplan-pages/{$estimate->id}/p1.png");
    // The non-floorplan portion of debug_log is preserved on retry.
    expect($estimate->debug_log['request']['kept'] ?? null)->toBeTrue();
    expect($estimate->debug_log)->not->toHaveKey('floorplan');
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
