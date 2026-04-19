<?php

use App\Enums\EstimateStatus;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\EstimateLineItem;
use App\Models\EstimateRoom;
use App\Models\Project;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create();

    $this->get(route('estimates.edit', $estimate))
        ->assertRedirect(route('login'));
});

test('another account cannot view the estimate', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create();

    $intruder = Account::factory()->create();

    $this->actingAs($intruder->owner)
        ->get(route('estimates.edit', $estimate))
        ->assertNotFound();
});

test('backfills line items on first visit to a completed estimate with none', function () {
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
            'project_wide' => [
                'customer_type' => 'person',
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

    expect(EstimateLineItem::where('estimate_id', $estimate->id)->count())->toBe(0);

    $this->actingAs($account->owner)
        ->get(route('estimates.edit', $estimate))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('estimates/edit')
            ->has('line_items')
            ->where('interview.is_complete', true)
        );

    expect(EstimateLineItem::where('estimate_id', $estimate->id)->count())->toBeGreaterThan(0);
});

test('backfill does not run when line items already exist', function () {
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
            'project_wide' => [
                'customer_type' => 'person',
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

    EstimateLineItem::factory()->create([
        'estimate_id' => $estimate->id,
        'key' => 'install_lvp',
        'unit_price' => 7.77,
    ]);

    $this->actingAs($account->owner)
        ->get(route('estimates.edit', $estimate))
        ->assertOk();

    $item = EstimateLineItem::where('estimate_id', $estimate->id)
        ->where('key', 'install_lvp')
        ->first();
    expect($item->unit_price)->toBe('7.77');
});

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

test('edit payload includes customer and project for the record header', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create([
        'account_id' => $account->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ]);
    $project = Project::factory()->forCustomer($customer)->create([
        'name' => 'Kitchen remodel',
    ]);
    $estimate = Estimate::factory()->forProject($project)->create();

    $this->actingAs($account->owner)
        ->get(route('estimates.edit', $estimate))
        ->assertInertia(fn (Assert $page) => $page
            ->component('estimates/edit')
            ->where('estimate.customer.id', $customer->id)
            ->where('estimate.customer.first_name', 'Ada')
            ->where('estimate.project.id', $project->id)
            ->where('estimate.project.name', 'Kitchen remodel')
        );
});

test('edit payload includes an absolute approval_url when the quote is sent', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->quoteSent()->create([
        'approval_token' => '01hxyz0000000000000000appr1',
    ]);

    $this->actingAs($account->owner)
        ->get(route('estimates.edit', $estimate))
        ->assertInertia(fn (Assert $page) => $page
            ->component('estimates/edit')
            ->where('estimate.approval_url', route('approve.show', ['approval_token' => '01hxyz0000000000000000appr1']))
        );
});

test('edit payload approval_url is null when the quote has not been sent', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create();

    $this->actingAs($account->owner)
        ->get(route('estimates.edit', $estimate))
        ->assertInertia(fn (Assert $page) => $page
            ->component('estimates/edit')
            ->where('estimate.approval_url', null)
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
            'project_wide' => [
                'customer_type' => 'person',
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
