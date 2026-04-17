<?php

use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Project;

test('members can update a project', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $project = Project::factory()->forCustomer($customer)->create(['name' => 'Old name']);

    $this->actingAs($user)
        ->patch(route('projects.update', $project), [
            'name' => 'New name',
            'site_address_line_1' => '123 Site Ave',
            'site_city' => 'Dallas',
            'site_state' => 'TX',
            'site_zip' => '75201',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'project-updated');

    $project->refresh();
    expect($project->name)->toBe('New name');
    expect($project->site_address_line_1)->toBe('123 Site Ave');
    expect($project->site_city)->toBe('Dallas');

    expect(ActivityLog::where('event', 'project.updated')->count())->toBe(1);
});

test('project name is required on update', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $project = Project::factory()->forCustomer($customer)->create();

    $this->actingAs($user)
        ->patch(route('projects.update', $project), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('update trims strings and blanks optionals back to null', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $project = Project::factory()->forCustomer($customer)->create([
        'name' => 'Original',
        'site_city' => 'Austin',
        'site_state' => 'TX',
        'notes' => 'Old note',
    ]);

    $this->actingAs($user)
        ->patch(route('projects.update', $project), [
            'name' => '  Renamed  ',
            'site_address_line_1' => '   ',
            'site_city' => '  Dallas  ',
            'site_state' => '',
            'notes' => '',
        ])
        ->assertRedirect();

    $project->refresh();
    expect($project->name)->toBe('Renamed');
    expect($project->site_city)->toBe('Dallas');
    expect($project->site_address_line_1)->toBeNull();
    expect($project->site_state)->toBeNull();
    expect($project->notes)->toBeNull();
});

test('cannot update a project from another account', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $otherAccount = Account::factory()->create();
    $otherCustomer = Customer::factory()->create(['account_id' => $otherAccount->id]);
    $otherProject = Project::factory()->forCustomer($otherCustomer)->create();

    $this->actingAs($user)
        ->patch(route('projects.update', $otherProject), ['name' => 'hijacked'])
        ->assertNotFound();
});
