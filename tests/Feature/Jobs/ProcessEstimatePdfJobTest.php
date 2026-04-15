<?php

use App\Ai\Agents\FloorPlanExtractionAgent;
use App\Enums\ActivityEvent;
use App\Enums\AiAgentKind;
use App\Enums\EstimateStatus;
use App\Jobs\ProcessEstimatePdfJob;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\AiAgentSetting;
use App\Models\Customer;
use App\Models\Estimate;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Ai;

beforeEach(function () {
    AiAgentSetting::factory()->create([
        'kind' => AiAgentKind::FloorPlanExtraction->value,
    ]);

    Storage::fake('local');
});

test('successful extraction fills the estimate and its rooms', function () {
    Ai::fakeAgent(FloorPlanExtractionAgent::class, [
        [
            'title' => 'Smith main floor',
            'total_sqft' => 1250,
            'rooms' => [
                ['name' => 'Kitchen', 'page' => 1, 'sqft' => 200, 'perimeter' => 60],
                ['name' => 'Living Room', 'page' => 1, 'sqft' => 400], // no perimeter -> fallback
            ],
            'errors' => [],
        ],
    ]);

    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    Storage::disk('local')->put('estimate-pdfs/abc.pdf', 'pdfcontents');
    $estimate = Estimate::factory()->forCustomer($customer)->processing()->create([
        'pdf_path' => 'estimate-pdfs/abc.pdf',
    ]);

    (new ProcessEstimatePdfJob($estimate->id))->handle();

    $estimate->refresh();
    expect($estimate->status)->toBe(EstimateStatus::Ready);
    expect($estimate->title)->toBe('Smith main floor');
    expect($estimate->total_sqft)->toBe(1250);
    expect($estimate->rooms)->toHaveCount(2);

    $kitchen = $estimate->rooms->firstWhere('name', 'Kitchen');
    expect($kitchen->sqft)->toBe(200);
    expect($kitchen->linear_feet)->toBe(60);
    expect($kitchen->position)->toBe(0);

    $living = $estimate->rooms->firstWhere('name', 'Living Room');
    // 4 * sqrt(400) = 80
    expect($living->linear_feet)->toBe(80);
    expect($living->position)->toBe(1);
});

test('agent errors are stored and the estimate is marked ready when other fields are present', function () {
    Ai::fakeAgent(FloorPlanExtractionAgent::class, [
        [
            'title' => 'Partial read',
            'total_sqft' => 600,
            'rooms' => [],
            'errors' => ['Page 2 was blank.'],
        ],
    ]);

    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    Storage::disk('local')->put('estimate-pdfs/a.pdf', 'pdfcontents');
    $estimate = Estimate::factory()->forCustomer($customer)->processing()->create([
        'pdf_path' => 'estimate-pdfs/a.pdf',
    ]);

    (new ProcessEstimatePdfJob($estimate->id))->handle();

    $estimate->refresh();
    expect($estimate->status)->toBe(EstimateStatus::Ready);
    expect($estimate->agent_errors)->toBe(['Page 2 was blank.']);
});

test('a thrown exception flips the estimate to failed and logs an activity event', function () {
    Ai::fakeAgent(FloorPlanExtractionAgent::class, function () {
        throw new RuntimeException('Provider rejected the PDF');
    });

    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    Storage::disk('local')->put('estimate-pdfs/a.pdf', 'pdfcontents');
    $estimate = Estimate::factory()->forCustomer($customer)->processing()->create([
        'pdf_path' => 'estimate-pdfs/a.pdf',
    ]);

    try {
        (new ProcessEstimatePdfJob($estimate->id))->handle();
    } catch (Throwable $e) {
        // expected; Laravel would normally call failed() after this.
    }

    $estimate->refresh();
    expect($estimate->status)->toBe(EstimateStatus::Failed);
    expect($estimate->agent_errors)->toContain('Provider rejected the PDF');
    expect(
        ActivityLog::where('event', ActivityEvent::EstimateAgentFailed)->count()
    )->toBe(1);
});
