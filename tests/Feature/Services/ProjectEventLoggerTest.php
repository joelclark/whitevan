<?php

use App\Contexts\ImpersonationContext;
use App\Enums\ActivityEvent;
use App\Enums\ActorType;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\ProjectEvent;
use App\Services\ProjectEventLogger;

function makeProject(?Account $account = null): Project
{
    $account ??= Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);

    return Project::factory()->forCustomer($customer)->create();
}

test('records a row scoped to a project with account derived from the subject', function () {
    $project = makeProject();

    ProjectEventLogger::record($project, ActivityEvent::ProjectCreated);

    $event = ProjectEvent::withoutGlobalScopes()->first();
    expect($event)->not->toBeNull();
    expect($event->account_id)->toBe($project->account_id);
    expect($event->project_id)->toBe($project->id);
    expect($event->estimate_id)->toBeNull();
    expect($event->event)->toBe(ActivityEvent::ProjectCreated);
    expect($event->customer_visible)->toBeTrue();
});

test('records estimate_id when the subject is an estimate', function () {
    $project = makeProject();
    $estimate = Estimate::factory()->forProject($project)->create();

    ProjectEventLogger::record($estimate, ActivityEvent::EstimateCreated);

    $event = ProjectEvent::withoutGlobalScopes()->first();
    expect($event->project_id)->toBe($project->id);
    expect($event->estimate_id)->toBe($estimate->id);
    expect($event->account_id)->toBe($project->account_id);
});

test('customer_visible is pulled from the enum', function () {
    $project = makeProject();

    ProjectEventLogger::record($project, ActivityEvent::ProjectCreated);
    ProjectEventLogger::record($project, ActivityEvent::ProjectUpdated);

    $rows = ProjectEvent::withoutGlobalScopes()->orderBy('id')->get();
    expect($rows[0]->customer_visible)->toBeTrue();
    expect($rows[1]->customer_visible)->toBeFalse();
});

test('actor_type defaults to Contractor when a user is provided', function () {
    $project = makeProject();

    ProjectEventLogger::record($project, ActivityEvent::ProjectUpdated, user: $project->account->owner);

    $event = ProjectEvent::withoutGlobalScopes()->first();
    expect($event->actor_type)->toBe(ActorType::Contractor);
    expect($event->user_id)->toBe($project->account->owner_user_id);
});

test('actor_type defaults to System when no user is provided', function () {
    $project = makeProject();

    ProjectEventLogger::record($project, ActivityEvent::ProjectUpdated);

    $event = ProjectEvent::withoutGlobalScopes()->first();
    expect($event->actor_type)->toBe(ActorType::System);
    expect($event->user_id)->toBeNull();
});

test('explicit actor_type overrides the default', function () {
    $project = makeProject();

    ProjectEventLogger::record(
        $project,
        ActivityEvent::EstimateQuoteSent,
        actorType: ActorType::Customer,
    );

    $event = ProjectEvent::withoutGlobalScopes()->first();
    expect($event->actor_type)->toBe(ActorType::Customer);
    expect($event->user_id)->toBeNull();
});

test('stamps metadata.impersonated when impersonating', function () {
    $project = makeProject();

    $impersonation = app(ImpersonationContext::class);
    $impersonation->start(Account::factory()->create());

    try {
        ProjectEventLogger::record(
            $project,
            ActivityEvent::ProjectCreated,
            metadata: ['project_name' => 'Kitchen'],
        );
    } finally {
        $impersonation->stop();
    }

    $event = ProjectEvent::withoutGlobalScopes()->first();
    expect($event->metadata)->toBe([
        'project_name' => 'Kitchen',
        'impersonated' => true,
    ]);
});

test('impersonation flag wins over a caller-supplied value', function () {
    $project = makeProject();

    $impersonation = app(ImpersonationContext::class);
    $impersonation->start(Account::factory()->create());

    try {
        ProjectEventLogger::record(
            $project,
            ActivityEvent::ProjectCreated,
            metadata: ['impersonated' => false],
        );
    } finally {
        $impersonation->stop();
    }

    $event = ProjectEvent::withoutGlobalScopes()->first();
    expect($event->metadata['impersonated'])->toBeTrue();
});
