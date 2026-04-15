<?php

use App\Enums\ActivityEvent;
use App\Enums\FloorplanAssetsStatus;
use App\Jobs\ExtractEstimateFloorplanAssetsJob;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Estimate;
use App\Services\FloorplanPageRenderer;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

/**
 * Stubs the renderer for tests. Records calls and writes a fake PNG so the
 * job can read width/height back via getimagesize().
 */
function fakeRenderer(int $totalPages, ?Closure $renderHook = null): FloorplanPageRenderer
{
    return new class($totalPages, $renderHook) extends FloorplanPageRenderer
    {
        /** @var array<int, array{page:int, output:string}> */
        public array $rendered = [];

        public function __construct(public int $totalPages, public ?Closure $renderHook) {}

        public function pageCount(string $pdfAbsolutePath): int
        {
            return $this->totalPages;
        }

        public function render(string $pdfAbsolutePath, int $page, string $outputAbsolutePathNoExt): string
        {
            $this->rendered[] = ['page' => $page, 'output' => $outputAbsolutePathNoExt];

            if ($this->renderHook !== null) {
                ($this->renderHook)($page);
            }

            $finalPath = $outputAbsolutePathNoExt.'.png';

            // Smallest possible PNG (1x1 transparent).
            file_put_contents(
                $finalPath,
                base64_decode(
                    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkAAIAAAoAAv/lxKUAAAAASUVORK5CYII=',
                ),
            );

            return $finalPath;
        }
    };
}

function bootEstimateWithRooms(array $pages): Estimate
{
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    Storage::disk('local')->put('estimate-pdfs/abc.pdf', 'pdfcontents');

    $estimate = Estimate::factory()->forCustomer($customer)->create([
        'pdf_path' => 'estimate-pdfs/abc.pdf',
        'floorplan_assets_status' => FloorplanAssetsStatus::Pending,
    ]);

    foreach ($pages as $i => $page) {
        $estimate->rooms()->create([
            'name' => 'Room '.($i + 1),
            'page' => $page,
            'sqft' => 100,
            'linear_feet' => 40,
            'position' => $i,
        ]);
    }

    return $estimate;
}

test('renders one png per unique room page and marks ready', function () {
    $renderer = fakeRenderer(totalPages: 5);
    $this->instance(FloorplanPageRenderer::class, $renderer);

    // 3 rooms across 2 unique pages (1 and 3).
    $estimate = bootEstimateWithRooms([1, 1, 3]);

    (new ExtractEstimateFloorplanAssetsJob($estimate->id))->handle(app(FloorplanPageRenderer::class));

    $estimate->refresh();
    expect($estimate->floorplan_assets_status)->toBe(FloorplanAssetsStatus::Ready);
    expect($estimate->floorplanPages)->toHaveCount(2);
    expect($estimate->floorplanPages->pluck('page')->all())->toBe([1, 3]);

    foreach ($estimate->floorplanPages as $row) {
        expect($row->image_path)->toBe("estimate-floorplan-pages/{$estimate->id}/p{$row->page}.png");
        Storage::disk('local')->assertExists($row->image_path);
        // Width/height come from getimagesize on the 1x1 PNG fake.
        expect($row->width)->toBe(1);
        expect($row->height)->toBe(1);
    }

    expect(count($renderer->rendered))->toBe(2);
});

test('per-page render failure does not fail the whole job', function () {
    $renderer = fakeRenderer(
        totalPages: 5,
        renderHook: function (int $page): void {
            if ($page === 2) {
                throw new RuntimeException('pdftoppm crashed on page 2');
            }
        },
    );
    $this->instance(FloorplanPageRenderer::class, $renderer);

    $estimate = bootEstimateWithRooms([1, 2, 3]);

    (new ExtractEstimateFloorplanAssetsJob($estimate->id))->handle(app(FloorplanPageRenderer::class));

    $estimate->refresh();
    expect($estimate->floorplan_assets_status)->toBe(FloorplanAssetsStatus::Ready);
    expect($estimate->floorplanPages->pluck('page')->all())->toBe([1, 3]);

    $debug = $estimate->debug_log['floorplan'] ?? null;
    expect($debug)->not->toBeNull();
    expect($debug['pages_failed'])->toHaveCount(1);
    expect($debug['pages_failed'][0]['page'])->toBe(2);
    expect($debug['pages_failed'][0]['error'])->toContain('pdftoppm crashed');
});

test('out-of-range room pages are skipped, not failed', function () {
    $renderer = fakeRenderer(totalPages: 2);
    $this->instance(FloorplanPageRenderer::class, $renderer);

    $estimate = bootEstimateWithRooms([1, 99]);

    (new ExtractEstimateFloorplanAssetsJob($estimate->id))->handle(app(FloorplanPageRenderer::class));

    $estimate->refresh();
    expect($estimate->floorplan_assets_status)->toBe(FloorplanAssetsStatus::Ready);
    expect($estimate->floorplanPages->pluck('page')->all())->toBe([1]);
    expect($estimate->debug_log['floorplan']['pages_skipped'])->toHaveCount(1);
});

test('all pages failing flips status to failed and logs an activity event', function () {
    $renderer = fakeRenderer(totalPages: 1);
    $this->instance(FloorplanPageRenderer::class, $renderer);

    // page 99 is out of range for a 1-page PDF, so all pages are skipped
    $estimate = bootEstimateWithRooms([99]);

    (new ExtractEstimateFloorplanAssetsJob($estimate->id))->handle(app(FloorplanPageRenderer::class));

    $estimate->refresh();
    expect($estimate->floorplan_assets_status)->toBe(FloorplanAssetsStatus::Failed);
    expect($estimate->floorplanPages)->toHaveCount(0);
    expect(
        ActivityLog::where('event', ActivityEvent::EstimateFloorplanAssetsFailed)->count()
    )->toBe(1);
});

test('rerun wipes existing rows and files before rendering', function () {
    $renderer = fakeRenderer(totalPages: 5);
    $this->instance(FloorplanPageRenderer::class, $renderer);

    $estimate = bootEstimateWithRooms([1]);

    // Pre-existing stale row + file from a hypothetical previous run.
    Storage::disk('local')->put("estimate-floorplan-pages/{$estimate->id}/p7.png", 'oldcontents');
    $estimate->floorplanPages()->create([
        'page' => 7,
        'image_path' => "estimate-floorplan-pages/{$estimate->id}/p7.png",
        'width' => 100,
        'height' => 100,
    ]);

    (new ExtractEstimateFloorplanAssetsJob($estimate->id))->handle(app(FloorplanPageRenderer::class));

    $estimate->refresh();
    expect($estimate->floorplanPages->pluck('page')->all())->toBe([1]);
    Storage::disk('local')->assertMissing("estimate-floorplan-pages/{$estimate->id}/p7.png");
    Storage::disk('local')->assertExists("estimate-floorplan-pages/{$estimate->id}/p1.png");
});

test('renderer process failure surfaces as floorplan_assets_status failed', function () {
    $renderer = new class extends FloorplanPageRenderer
    {
        public function pageCount(string $pdfAbsolutePath): int
        {
            throw new RuntimeException('poppler-utils (pdfinfo) is not installed or not on PATH.');
        }
    };
    $this->instance(FloorplanPageRenderer::class, $renderer);

    $estimate = bootEstimateWithRooms([1]);

    (new ExtractEstimateFloorplanAssetsJob($estimate->id))->handle(app(FloorplanPageRenderer::class));

    $estimate->refresh();
    expect($estimate->floorplan_assets_status)->toBe(FloorplanAssetsStatus::Failed);
    expect($estimate->debug_log['floorplan']['errors'][0])->toContain('poppler-utils');
});
