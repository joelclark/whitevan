<?php

use App\Models\Account;
use App\Models\Customer;
use App\Models\Estimate;
use Illuminate\Support\Facades\Storage;

test('members can download the PDF for an estimate they own', function () {
    Storage::fake('local');

    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);

    Storage::disk('local')->put('estimate-pdfs/xyz.pdf', 'pdfcontents');

    $estimate = Estimate::factory()->forCustomer($customer)->create([
        'pdf_path' => 'estimate-pdfs/xyz.pdf',
        'pdf_original_filename' => 'original.pdf',
    ]);

    $this->actingAs($user)
        ->get(route('estimates.pdf', $estimate))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('cross-tenant PDF download is blocked', function () {
    Storage::fake('local');

    $otherAccount = Account::factory()->create();
    $otherCustomer = Customer::factory()->create(['account_id' => $otherAccount->id]);
    Storage::disk('local')->put('estimate-pdfs/xyz.pdf', 'pdfcontents');
    $estimate = Estimate::factory()->forCustomer($otherCustomer)->create([
        'pdf_path' => 'estimate-pdfs/xyz.pdf',
    ]);

    $account = Account::factory()->create();

    $this->actingAs($account->owner)
        ->get(route('estimates.pdf', $estimate))
        ->assertNotFound();
});
