<?php

namespace App\Jobs;

use App\Enums\ActivityEvent;
use App\Enums\FloorplanAssetsStatus;
use App\Models\Estimate;
use App\Services\ActivityLogger;
use App\Services\FloorplanPageRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Renders the unique room pages of an estimate's PDF into PNG previews.
 *
 * Runs after ProcessEstimatePdfJob has saved the room rows. Failure here is
 * independent from the AI job — the estimate stays Ready and only the
 * floorplan_assets_status flips to Failed. Tries = 1: shelling out is not
 * something a retry would make succeed (binary missing, malformed PDF), and
 * the user can re-run via the existing retry endpoint.
 */
class ExtractEstimateFloorplanAssetsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $estimateId) {}

    public function handle(FloorplanPageRenderer $renderer): void
    {
        // Jobs run outside the request lifecycle, so the AccountContext
        // global scope would hide this row. Bypass it.
        $estimate = Estimate::withoutGlobalScopes()
            ->with(['rooms', 'floorplanPages'])
            ->findOrFail($this->estimateId);

        if ($estimate->pdf_path === null || $estimate->pdf_path === '') {
            $this->persistFailure($estimate, ['Estimate has no PDF on disk.']);

            return;
        }

        $absolutePdfPath = Storage::disk('local')->path($estimate->pdf_path);

        if (! is_file($absolutePdfPath)) {
            $this->persistFailure($estimate, ['PDF file is missing from disk: '.$estimate->pdf_path]);

            return;
        }

        // Wipe any previous run's rows + files so reruns are idempotent.
        $this->cleanupExisting($estimate);

        $pages = $estimate->rooms
            ->pluck('page')
            ->filter(fn ($p) => is_numeric($p) && (int) $p > 0)
            ->map(fn ($p) => (int) $p)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($pages === []) {
            $this->persistFailure($estimate, ['No room pages to render.']);

            return;
        }

        try {
            $totalPages = $renderer->pageCount($absolutePdfPath);
        } catch (Throwable $e) {
            $this->persistFailure($estimate, ['pageCount: '.$e->getMessage()]);

            return;
        }

        $rendered = [];
        $skipped = [];
        $failed = [];

        foreach ($pages as $page) {
            if ($page < 1 || $page > $totalPages) {
                $skipped[] = ['page' => $page, 'reason' => "page out of range (PDF has {$totalPages} pages)"];

                continue;
            }

            $relativeDir = "estimate-floorplan-pages/{$estimate->id}";
            $relativePath = "{$relativeDir}/p{$page}.png";
            $absoluteDir = Storage::disk('local')->path($relativeDir);
            $absolutePathNoExt = Storage::disk('local')->path("{$relativeDir}/p{$page}");

            try {
                File::ensureDirectoryExists($absoluteDir);
                $written = $renderer->render($absolutePdfPath, $page, $absolutePathNoExt);

                [$width, $height] = $this->measure($written);

                $rendered[] = [
                    'page' => $page,
                    'image_path' => $relativePath,
                    'width' => $width,
                    'height' => $height,
                ];
            } catch (Throwable $e) {
                // Best-effort cleanup of any partial file.
                Storage::disk('local')->delete($relativePath);
                $failed[] = ['page' => $page, 'error' => $e->getMessage()];
            }
        }

        if ($rendered === []) {
            $this->persistFailure(
                $estimate,
                ['No pages were rendered.'],
                skipped: $skipped,
                failed: $failed,
            );

            return;
        }

        DB::transaction(function () use ($estimate, $rendered, $skipped, $failed): void {
            foreach ($rendered as $row) {
                $estimate->floorplanPages()->create($row);
            }

            $estimate->forceFill([
                'floorplan_assets_status' => FloorplanAssetsStatus::Ready,
                'debug_log' => $this->mergeDebugLog($estimate, [
                    'status' => 'success',
                    'at' => now()->toIso8601String(),
                    'rendered' => array_map(fn ($r) => $r['page'], $rendered),
                    'pages_skipped' => $skipped,
                    'pages_failed' => $failed,
                ]),
            ])->save();
        });
    }

    public function failed(Throwable $exception): void
    {
        $estimate = Estimate::withoutGlobalScopes()->find($this->estimateId);

        // handle() persists Failed inline, so guard against double-write —
        // mirrors ProcessEstimatePdfJob::failed() at line 87.
        if ($estimate !== null && $estimate->floorplan_assets_status !== FloorplanAssetsStatus::Failed) {
            $this->persistFailure($estimate, [$exception->getMessage()]);
        }
    }

    private function cleanupExisting(Estimate $estimate): void
    {
        $existing = $estimate->floorplanPages()->get();

        if ($existing->isEmpty()) {
            return;
        }

        Storage::disk('local')->delete($existing->pluck('image_path')->all());
        $estimate->floorplanPages()->delete();
    }

    /**
     * @return array{0:int,1:int}
     */
    private function measure(string $absolutePngPath): array
    {
        $info = @getimagesize($absolutePngPath);

        if ($info === false) {
            return [0, 0];
        }

        return [(int) $info[0], (int) $info[1]];
    }

    /**
     * @param  array<int, string>  $errors
     * @param  array<int, array<string, mixed>>  $skipped
     * @param  array<int, array<string, mixed>>  $failed
     */
    private function persistFailure(
        Estimate $estimate,
        array $errors,
        array $skipped = [],
        array $failed = [],
    ): void {
        $estimate->forceFill([
            'floorplan_assets_status' => FloorplanAssetsStatus::Failed,
            'debug_log' => $this->mergeDebugLog($estimate, [
                'status' => 'failed',
                'at' => now()->toIso8601String(),
                'errors' => $errors,
                'pages_skipped' => $skipped,
                'pages_failed' => $failed,
            ]),
        ])->save();

        ActivityLogger::event(
            ActivityEvent::EstimateFloorplanAssetsFailed,
            metadata: [
                'estimate_id' => $estimate->id,
                'errors' => $errors,
            ],
            account: $estimate->account,
        );
    }

    /**
     * Merge a `floorplan` namespace into the existing debug_log without
     * disturbing the AI agent's request/response payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mergeDebugLog(Estimate $estimate, array $payload): array
    {
        $existing = is_array($estimate->debug_log) ? $estimate->debug_log : [];
        $existing['floorplan'] = $payload;

        return $existing;
    }
}
