<?php

use App\Models\Account;
use App\Models\Customer;
use App\Models\Estimate;
use Illuminate\Support\Facades\Storage;

test('members can fetch a rendered floorplan page image', function () {
    Storage::fake('local');

    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create();

    Storage::disk('local')->put("estimate-floorplan-pages/{$estimate->id}/p2.png", 'pngbytes');
    $estimate->floorplanPages()->create([
        'page' => 2,
        'image_path' => "estimate-floorplan-pages/{$estimate->id}/p2.png",
        'width' => 1275,
        'height' => 1650,
    ]);

    $this->actingAs($user)
        ->get(route('estimates.floorplan-page', ['estimate' => $estimate->id, 'page' => 2]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'max-age=3600, private');
});

test('missing page row returns 404', function () {
    Storage::fake('local');

    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create();

    $this->actingAs($user)
        ->get(route('estimates.floorplan-page', ['estimate' => $estimate->id, 'page' => 1]))
        ->assertNotFound();
});

test('missing image file on disk returns 404 even if the row exists', function () {
    Storage::fake('local');

    $account = Account::factory()->create();
    $user = $account->owner;
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create();

    $estimate->floorplanPages()->create([
        'page' => 1,
        'image_path' => "estimate-floorplan-pages/{$estimate->id}/p1.png",
        'width' => 100,
        'height' => 100,
    ]);

    $this->actingAs($user)
        ->get(route('estimates.floorplan-page', ['estimate' => $estimate->id, 'page' => 1]))
        ->assertNotFound();
});

test('cross-tenant floorplan page fetch is blocked', function () {
    Storage::fake('local');

    $otherAccount = Account::factory()->create();
    $otherCustomer = Customer::factory()->create(['account_id' => $otherAccount->id]);
    $estimate = Estimate::factory()->forCustomer($otherCustomer)->create();

    Storage::disk('local')->put("estimate-floorplan-pages/{$estimate->id}/p1.png", 'pngbytes');
    $estimate->floorplanPages()->create([
        'page' => 1,
        'image_path' => "estimate-floorplan-pages/{$estimate->id}/p1.png",
        'width' => 100,
        'height' => 100,
    ]);

    $intruderAccount = Account::factory()->create();

    $this->actingAs($intruderAccount->owner)
        ->get(route('estimates.floorplan-page', ['estimate' => $estimate->id, 'page' => 1]))
        ->assertNotFound();
});
