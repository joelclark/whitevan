<?php

use App\Enums\ActivityEvent;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\ProjectEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;

test('members can delete a project', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $project = Project::factory()->forCustomer($customer)->create();

    $this->actingAs($user)
        ->delete(route('projects.destroy', $project))
        ->assertRedirect(route('customers.edit', $customer->id))
        ->assertSessionHas('status', 'project-deleted');

    expect(Project::withoutGlobalScopes()->find($project->id)->deleted_at)
        ->not->toBeNull();

    expect(ActivityLog::where('event', 'project.deleted')->count())->toBe(1);
    expect($project)->toHaveRecordedProjectEvent(ActivityEvent::ProjectDeleted);
});

test('deleting a project cascades (soft) to its estimates and cleans files', function () {
    Storage::fake('local');

    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $project = Project::factory()->forCustomer($customer)->create();

    Storage::disk('local')->put('estimate-pdfs/doomed.pdf', 'pdf-bytes');
    $estimate = Estimate::factory()->forProject($project)->create([
        'pdf_path' => 'estimate-pdfs/doomed.pdf',
    ]);

    $this->actingAs($user)->delete(route('projects.destroy', $project));

    expect(Estimate::withoutGlobalScopes()->find($estimate->id)->deleted_at)
        ->not->toBeNull();
    Storage::disk('local')->assertMissing('estimate-pdfs/doomed.pdf');
});

test('a failed delete does not write a project_events or activity_log row', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $project = Project::factory()->forCustomer($customer)->create();

    // Simulate a DB failure at delete time so the controller hits the
    // QueryException branch. We throw from a `deleting` listener instead
    // of actually corrupting the schema.
    Project::deleting(function () {
        throw new QueryException(
            'test',
            'DELETE FROM projects',
            [],
            new RuntimeException('forced failure'),
        );
    });

    $this->actingAs($user)
        ->delete(route('projects.destroy', $project))
        ->assertSessionHas('status', 'project-delete-failed');

    expect(Project::withoutGlobalScopes()->find($project->id)->deleted_at)->toBeNull();
    expect(ProjectEvent::where('project_id', $project->id)
        ->where('event', ActivityEvent::ProjectDeleted->value)
        ->exists())->toBeFalse();
    expect(ActivityLog::where('event', ActivityEvent::ProjectDeleted->value)->count())->toBe(0);
});

test('cannot delete a project from another account', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $otherAccount = Account::factory()->create();
    $otherCustomer = Customer::factory()->create(['account_id' => $otherAccount->id]);
    $otherProject = Project::factory()->forCustomer($otherCustomer)->create();

    $this->actingAs($user)
        ->delete(route('projects.destroy', $otherProject))
        ->assertNotFound();

    expect(Project::withoutGlobalScopes()->find($otherProject->id)->deleted_at)
        ->toBeNull();
});
