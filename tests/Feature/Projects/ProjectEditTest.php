<?php

use App\Enums\ActivityEvent;
use App\Enums\ActorType;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\ProjectEvent;

test('guests cannot view a project', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $project = Project::factory()->forCustomer($customer)->create();

    $this->get(route('projects.edit', $project))->assertRedirect(route('login'));
});

test('members can view a project', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $project = Project::factory()->forCustomer($customer)->create(['name' => 'Basement']);
    Estimate::factory()->forProject($project)->create();

    $this->actingAs($user)
        ->get(route('projects.edit', $project))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('projects/edit')
                ->where('project.id', $project->id)
                ->where('project.name', 'Basement')
                ->has('project.customer')
                ->has('project.estimates', 1),
        );
});

test('events prop is shipped in reverse chronological order with actor and estimate details', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $project = Project::factory()->forCustomer($customer)->create();
    $estimate = Estimate::factory()->forProject($project)->create(['title' => 'Kitchen floor plan']);

    ProjectEvent::factory()->forProject($project)->forUser($user)->create([
        'event' => ActivityEvent::ProjectCreated,
        'customer_visible' => true,
        'created_at' => now()->subHour(),
    ]);
    ProjectEvent::factory()->forEstimate($estimate)->create([
        'event' => ActivityEvent::EstimateQuoteSent,
        'actor_type' => ActorType::Customer,
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('projects.edit', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/edit')
            ->has('events', 2)
            ->where('events.0.event', ActivityEvent::EstimateQuoteSent->value)
            ->where('events.0.event_label', 'Quote sent')
            ->where('events.0.actor_type', 'customer')
            ->where('events.0.estimate_id', $estimate->id)
            ->where('events.0.estimate_title', 'Kitchen floor plan')
            ->where('events.1.event', ActivityEvent::ProjectCreated->value)
            ->where('events.1.actor_type', 'contractor')
            ->where('events.1.actor_name', $user->name)
            ->where('events.1.estimate_id', null),
        );
});

test('timeline entries retain estimate details after the estimate is soft-deleted', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $project = Project::factory()->forCustomer($customer)->create();
    $estimate = Estimate::factory()->forProject($project)->create(['title' => 'Kitchen floor plan']);

    ProjectEvent::factory()->forEstimate($estimate)->create([
        'event' => ActivityEvent::EstimateDeleted,
        'created_at' => now(),
    ]);

    $estimate->delete();

    $this->actingAs($user)
        ->get(route('projects.edit', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/edit')
            ->where('events.0.event', ActivityEvent::EstimateDeleted->value)
            ->where('events.0.estimate_id', $estimate->id)
            ->where('events.0.estimate_title', 'Kitchen floor plan')
            ->where('events.0.estimate_deleted', true),
        );
});

test('timeline entries for live estimates report estimate_deleted=false', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $project = Project::factory()->forCustomer($customer)->create();
    $estimate = Estimate::factory()->forProject($project)->create(['title' => 'Kitchen floor plan']);

    ProjectEvent::factory()->forEstimate($estimate)->create([
        'event' => ActivityEvent::EstimateQuoteSent,
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('projects.edit', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/edit')
            ->where('events.0.estimate_id', $estimate->id)
            ->where('events.0.estimate_deleted', false),
        );
});

test('cannot view a project from another account', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $otherAccount = Account::factory()->create();
    $otherCustomer = Customer::factory()->create(['account_id' => $otherAccount->id]);
    $otherProject = Project::factory()->forCustomer($otherCustomer)->create();

    $this->actingAs($user)
        ->get(route('projects.edit', $otherProject))
        ->assertNotFound();
});
