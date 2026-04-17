<?php

use App\Models\Account;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Project;

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
