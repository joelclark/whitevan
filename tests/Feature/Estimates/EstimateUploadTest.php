<?php

use App\Enums\ActivityEvent;
use App\Enums\EstimateStatus;
use App\Jobs\ProcessEstimatePdfJob;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('guests cannot upload an estimate', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $project = Project::factory()->forCustomer($customer)->create();

    $this->post(route('projects.estimates.store', $project))
        ->assertRedirect(route('login'));
});

test('members can upload a PDF and create an estimate in processing status', function () {
    Storage::fake('local');
    Queue::fake();

    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $project = Project::factory()->forCustomer($customer)->create();

    $file = UploadedFile::fake()->create('floor-plan.pdf', 500, 'application/pdf');

    $this->actingAs($user)
        ->post(route('projects.estimates.store', $project), [
            'pdf' => $file,
        ])
        ->assertRedirect();

    $estimate = Estimate::query()->first();
    expect($estimate)->not->toBeNull();
    expect($estimate->account_id)->toBe($account->id);
    expect($estimate->project_id)->toBe($project->id);
    expect($estimate->project->customer_id)->toBe($customer->id);
    expect($estimate->status)->toBe(EstimateStatus::Processing);
    expect($estimate->pdf_original_filename)->toBe('floor-plan.pdf');
    Storage::disk('local')->assertExists($estimate->pdf_path);

    Queue::assertPushed(
        ProcessEstimatePdfJob::class,
        fn (ProcessEstimatePdfJob $job) => $job->estimateId === $estimate->id,
    );

    expect(ActivityLog::where('event', ActivityEvent::EstimateCreated)->count())->toBe(1);
});

test('only PDF files are accepted', function () {
    Storage::fake('local');
    Queue::fake();

    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $project = Project::factory()->forCustomer($customer)->create();

    $file = UploadedFile::fake()->create('drawing.png', 10, 'image/png');

    $this->actingAs($user)
        ->post(route('projects.estimates.store', $project), [
            'pdf' => $file,
        ])
        ->assertSessionHasErrors('pdf');

    expect(Estimate::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('a project in another account cannot be used', function () {
    Storage::fake('local');
    Queue::fake();

    $account = Account::factory()->create();
    $user = $account->owner;

    $otherAccount = Account::factory()->create();
    $otherCustomer = Customer::factory()->create(['account_id' => $otherAccount->id]);
    $otherProject = Project::factory()->forCustomer($otherCustomer)->create();

    $file = UploadedFile::fake()->create('floor-plan.pdf', 500, 'application/pdf');

    $this->actingAs($user)
        ->post(route('projects.estimates.store', $otherProject), [
            'pdf' => $file,
        ])
        ->assertNotFound();

    expect(Estimate::query()->count())->toBe(0);
});
