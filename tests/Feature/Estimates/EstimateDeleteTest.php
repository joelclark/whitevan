<?php

use App\Enums\ActivityEvent;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Estimate;
use Illuminate\Support\Facades\Storage;

test('deleting an estimate soft-deletes it and removes the PDF', function () {
    Storage::fake('local');

    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);

    Storage::disk('local')->put('estimate-pdfs/abc.pdf', 'pdfcontents');

    $estimate = Estimate::factory()->forCustomer($customer)->create([
        'pdf_path' => 'estimate-pdfs/abc.pdf',
    ]);

    $this->actingAs($user)
        ->delete(route('estimates.destroy', $estimate))
        ->assertRedirect(route('estimates.index'));

    expect(Estimate::withTrashed()->find($estimate->id)->deleted_at)->not->toBeNull();
    Storage::disk('local')->assertMissing('estimate-pdfs/abc.pdf');
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
