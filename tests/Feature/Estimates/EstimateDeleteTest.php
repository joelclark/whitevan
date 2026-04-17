<?php

use App\Enums\ActivityEvent;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Estimate;
use Illuminate\Support\Facades\Storage;

test('deleting an estimate soft-deletes it and removes the PDF and floorplan images', function () {
    Storage::fake('local');

    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);

    Storage::disk('local')->put('estimate-pdfs/abc.pdf', 'pdfcontents');

    $estimate = Estimate::factory()->forCustomer($customer)->create([
        'pdf_path' => 'estimate-pdfs/abc.pdf',
    ]);

    Storage::disk('local')->put("estimate-floorplan-pages/{$estimate->id}/p1.png", 'pngbytes');
    Storage::disk('local')->put("estimate-floorplan-pages/{$estimate->id}/p3.png", 'pngbytes');
    $estimate->floorplanPages()->createMany([
        ['page' => 1, 'image_path' => "estimate-floorplan-pages/{$estimate->id}/p1.png", 'width' => 100, 'height' => 100],
        ['page' => 3, 'image_path' => "estimate-floorplan-pages/{$estimate->id}/p3.png", 'width' => 100, 'height' => 100],
    ]);

    $this->actingAs($user)
        ->delete(route('estimates.destroy', $estimate))
        ->assertRedirect(route('projects.edit', $estimate->project_id));

    expect(Estimate::withTrashed()->find($estimate->id)->deleted_at)->not->toBeNull();
    Storage::disk('local')->assertMissing('estimate-pdfs/abc.pdf');
    Storage::disk('local')->assertMissing("estimate-floorplan-pages/{$estimate->id}/p1.png");
    Storage::disk('local')->assertMissing("estimate-floorplan-pages/{$estimate->id}/p3.png");
    expect(ActivityLog::where('event', ActivityEvent::EstimateDeleted)->count())->toBe(1);
});

test('cross-tenant delete is blocked', function () {
    $otherAccount = Account::factory()->create();
    $otherCustomer = Customer::factory()->create(['account_id' => $otherAccount->id]);
    $estimate = Estimate::factory()->forCustomer($otherCustomer)->create();

    $account = Account::factory()->create();

    $this->actingAs($account->owner)
        ->delete(route('estimates.destroy', $estimate))
        ->assertNotFound();

    $fresh = Estimate::withoutGlobalScopes()->withTrashed()->find($estimate->id);
    expect($fresh)->not->toBeNull();
    expect($fresh->deleted_at)->toBeNull();
});
