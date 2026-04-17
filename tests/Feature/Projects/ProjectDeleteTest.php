<?php

use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Project;
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
