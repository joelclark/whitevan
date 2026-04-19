<?php

use App\Enums\ActivityEvent;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Project;

test('guests cannot create a project', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);

    $this->post(route('customers.projects.store', $customer), ['name' => 'Kitchen'])
        ->assertRedirect(route('login'));
});

test('members can create a project for a customer', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);

    $this->actingAs($user)
        ->post(route('customers.projects.store', $customer), [
            'name' => 'Kitchen remodel',
            'site_city' => 'Austin',
            'site_state' => 'TX',
        ])
        ->assertRedirect();

    $project = Project::query()->first();
    expect($project)->not->toBeNull();
    expect($project->account_id)->toBe($account->id);
    expect($project->customer_id)->toBe($customer->id);
    expect($project->name)->toBe('Kitchen remodel');
    expect($project->site_city)->toBe('Austin');
    expect($project->site_state)->toBe('TX');

    expect(ActivityLog::where('event', 'project.created')->count())->toBe(1);
    expect($project)->toHaveRecordedProjectEvent(ActivityEvent::ProjectCreated);
});

test('project name is required', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);

    $this->actingAs($user)
        ->post(route('customers.projects.store', $customer), ['name' => ''])
        ->assertSessionHasErrors('name');

    expect(Project::query()->count())->toBe(0);
});

test('blank optional fields are coerced to null and leading/trailing whitespace trimmed', function () {
    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);

    $this->actingAs($user)
        ->post(route('customers.projects.store', $customer), [
            'name' => '  Kitchen  ',
            'site_address_line_1' => '   ',
            'site_address_line_2' => '',
            'site_city' => '  Austin  ',
            'site_state' => '',
            'site_zip' => '   ',
            'notes' => '',
        ])
        ->assertRedirect();

    $project = Project::query()->first();
    expect($project->name)->toBe('Kitchen');
    expect($project->site_city)->toBe('Austin');
    expect($project->site_address_line_1)->toBeNull();
    expect($project->site_address_line_2)->toBeNull();
    expect($project->site_state)->toBeNull();
    expect($project->site_zip)->toBeNull();
    expect($project->notes)->toBeNull();
});

test('cannot create a project under a customer from another account', function () {
    $account = Account::factory()->create();
    $user = $account->owner;

    $otherAccount = Account::factory()->create();
    $otherCustomer = Customer::factory()->create(['account_id' => $otherAccount->id]);

    $this->actingAs($user)
        ->post(route('customers.projects.store', $otherCustomer), ['name' => 'Sneak'])
        ->assertNotFound();

    expect(Project::query()->count())->toBe(0);
});
