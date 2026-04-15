<?php

use App\Enums\EstimateStatus;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\EstimateRoom;
use Inertia\Testing\AssertableInertia as Assert;

test('edit page includes an interview next_question when status is ready', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create([
        'status' => EstimateStatus::Ready,
    ]);
    EstimateRoom::factory()->create([
        'estimate_id' => $estimate->id,
        'position' => 1,
    ]);

    $this->actingAs($account->owner)
        ->get(route('estimates.edit', $estimate))
        ->assertInertia(fn (Assert $page) => $page
            ->component('estimates/edit')
            ->has('interview', fn (Assert $i) => $i
                ->where('is_complete', false)
                ->has('next_question', fn (Assert $q) => $q
                    ->where('key', 'material')
                    ->where('phase', 'room')
                    ->etc()
                )
                ->etc()
            )
        );
});

test('edit page has interview null when status is processing', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->processing()->create();

    $this->actingAs($account->owner)
        ->get(route('estimates.edit', $estimate))
        ->assertInertia(fn (Assert $page) => $page
            ->component('estimates/edit')
            ->where('interview', null)
        );
});

test('edit page reports is_complete when all questions are answered', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create([
        'status' => EstimateStatus::Ready,
        'interview_answers' => [
            'rooms' => [
                '1' => [
                    'material' => 'lvp',
                    'existing' => 'carpet',
                    'subfloor' => 'none',
                    'furniture' => 'empty',
                ],
            ],
            'long_tail' => [
                'demo_haul_away' => 'van',
                'baseboards' => 'leave',
                'quarter_round' => 'new',
                'transitions' => 2,
                'door_undercuts' => 1,
                'toilet_pulls' => 0,
            ],
        ],
    ]);
    EstimateRoom::factory()->create([
        'estimate_id' => $estimate->id,
        'id' => 1,
        'position' => 1,
    ]);

    $this->actingAs($account->owner)
        ->get(route('estimates.edit', $estimate))
        ->assertInertia(fn (Assert $page) => $page
            ->component('estimates/edit')
            ->has('interview', fn (Assert $i) => $i
                ->where('is_complete', true)
                ->where('next_question', null)
                ->etc()
            )
        );
});
