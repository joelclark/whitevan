<?php

use App\Enums\EstimateStatus;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\EstimateLineItem;
use App\Models\EstimateRoom;
use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function projectWithStaleActivity(?Account $account = null): Project
{
    $account ??= Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);

    $project = Project::factory()->forCustomer($customer)->create();
    // Rewind the denormalized timestamp so the bump site under test must
    // actually advance it — otherwise a test that never touched activity
    // would pass just from the DB default being "recent enough".
    $project->forceFill(['last_activity_at' => now()->subDays(3)])->save();

    return $project->refresh();
}

test('updating a project bumps last_activity_at', function () {
    $account = Account::factory()->create();
    $project = projectWithStaleActivity($account);
    $before = $project->last_activity_at;

    $this->actingAs($account->owner)
        ->patch(route('projects.update', $project), [
            'name' => 'Renamed project',
        ])
        ->assertRedirect();

    expect($project->fresh()->last_activity_at->greaterThan($before))->toBeTrue();
});

test('uploading an estimate on a project bumps last_activity_at', function () {
    Storage::fake('local');
    Queue::fake();

    $account = Account::factory()->create();
    $project = projectWithStaleActivity($account);
    $before = $project->last_activity_at;

    $this->actingAs($account->owner)
        ->post(route('projects.estimates.store', $project), [
            'pdf' => UploadedFile::fake()->create('floor-plan.pdf', 500, 'application/pdf'),
        ])
        ->assertRedirect();

    expect($project->fresh()->last_activity_at->greaterThan($before))->toBeTrue();
});

test('updating an estimate title bumps its project last_activity_at', function () {
    $account = Account::factory()->create();
    $project = projectWithStaleActivity($account);
    $estimate = Estimate::factory()->forProject($project)->create([
        'title' => 'Original title',
    ]);
    $before = $project->fresh()->last_activity_at;

    $this->actingAs($account->owner)
        ->patch(route('estimates.update', $estimate), [
            'title' => 'Renamed title',
        ])
        ->assertRedirect();

    expect($project->fresh()->last_activity_at->greaterThan($before))->toBeTrue();
});

test('deleting an estimate bumps its project last_activity_at', function () {
    $account = Account::factory()->create();
    $project = projectWithStaleActivity($account);
    $estimate = Estimate::factory()->forProject($project)->create();
    $before = $project->fresh()->last_activity_at;

    $this->actingAs($account->owner)
        ->delete(route('estimates.destroy', $estimate))
        ->assertRedirect();

    expect($project->fresh()->last_activity_at->greaterThan($before))->toBeTrue();
});

test('retrying an estimate bumps its project last_activity_at', function () {
    Queue::fake();

    $account = Account::factory()->create();
    $project = projectWithStaleActivity($account);
    $estimate = Estimate::factory()->forProject($project)->failed()->create();
    $before = $project->fresh()->last_activity_at;

    $this->actingAs($account->owner)
        ->post(route('estimates.retry', $estimate))
        ->assertRedirect();

    expect($project->fresh()->last_activity_at->greaterThan($before))->toBeTrue();
});

test('setting a line item price bumps its project last_activity_at', function () {
    $account = Account::factory()->create();
    $project = projectWithStaleActivity($account);
    $estimate = Estimate::factory()->forProject($project)->create();
    $item = EstimateLineItem::factory()->create([
        'estimate_id' => $estimate->id,
        'unit_price' => null,
    ]);
    $before = $project->fresh()->last_activity_at;

    $this->actingAs($account->owner)
        ->patch(route('estimates.line-items.update', [$estimate, $item]), [
            'unit_price' => 42.50,
        ])
        ->assertRedirect();

    expect($project->fresh()->last_activity_at->greaterThan($before))->toBeTrue();
});

test('answering an interview question bumps its project last_activity_at', function () {
    $account = Account::factory()->create();
    $project = projectWithStaleActivity($account);
    $estimate = Estimate::factory()->forProject($project)->create([
        'status' => EstimateStatus::Ready,
    ]);
    $room = EstimateRoom::factory()->create([
        'estimate_id' => $estimate->id,
        'position' => 1,
        'name' => 'Room 1',
    ]);
    $before = $project->fresh()->last_activity_at;

    $this->actingAs($account->owner)
        ->post(route('estimates.interview.answer', $estimate), [
            'question_key' => 'material',
            'room_id' => $room->id,
            'value' => 'lvp',
        ])
        ->assertRedirect();

    expect($project->fresh()->last_activity_at->greaterThan($before))->toBeTrue();
});

test('sending a quote bumps its project last_activity_at', function () {
    $account = Account::factory()->create();
    $project = projectWithStaleActivity($account);
    $estimate = Estimate::factory()->forProject($project)->create();
    EstimateLineItem::factory()->create([
        'estimate_id' => $estimate->id,
        'unit_price' => 5.50,
    ]);
    $before = $project->fresh()->last_activity_at;

    $this->actingAs($account->owner)
        ->post(route('estimates.send-quote', $estimate))
        ->assertRedirect();

    expect($project->fresh()->last_activity_at->greaterThan($before))->toBeTrue();
});
