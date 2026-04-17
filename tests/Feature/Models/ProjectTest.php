<?php

use App\Models\Account;
use App\Models\Customer;
use App\Models\Project;

test('newly created projects have last_activity_at populated', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);

    $project = Project::factory()->forCustomer($customer)->create();

    expect($project->last_activity_at)->not->toBeNull();
});

test('recordActivity() advances last_activity_at', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $project = Project::factory()->forCustomer($customer)->create();

    $initial = $project->last_activity_at;

    // Ensure the clock advances before the next write.
    $this->travel(1)->minutes();

    $project->recordActivity();

    expect($project->fresh()->last_activity_at->greaterThan($initial))->toBeTrue();
});

test('last_activity_at is not mass-assignable', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $project = Project::factory()->forCustomer($customer)->create();

    $initial = $project->last_activity_at;

    // Mass-assigned writes to a non-fillable column must be rejected —
    // the column is only written through recordActivity(), never via
    // user-supplied request input.
    $project->update(['last_activity_at' => now()->addYear()]);

    expect($project->fresh()->last_activity_at->toIso8601String())
        ->toBe($initial->toIso8601String());
});
