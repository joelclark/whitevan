<?php

namespace App\Jobs;

use App\Ai\Agents\FloorPlanExtractionAgent;
use App\Enums\ActivityEvent;
use App\Enums\AiAgentKind;
use App\Enums\EstimateStatus;
use App\Enums\FloorplanAssetsStatus;
use App\Models\AiAgentSetting;
use App\Models\Estimate;
use App\Services\ActivityLogger;
use App\Services\LinearFeetFallback;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\ObjectType;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Document;
use Throwable;

/**
 * Runs the floor plan extraction agent against an uploaded PDF and persists
 * the structured result to the estimate + its rooms.
 *
 * $tries = 1 because agent calls cost tokens. Failures flip the estimate to
 * `failed` and surface to the user in the UI; we never silently retry.
 */
class ProcessEstimatePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public int $estimateId) {}

    public function handle(): void
    {
        // Jobs run outside any request, so the AccountContext global scope
        // would silently hide the row. Bypass it here and re-hydrate by id.
        $estimate = Estimate::withoutGlobalScopes()->findOrFail($this->estimateId);

        $setting = AiAgentSetting::forKind(AiAgentKind::FloorPlanExtraction);
        $agent = new FloorPlanExtractionAgent($setting);

        $prompt = 'Extract the floor plan from this PDF and return JSON matching the response format.';
        $absolutePdfPath = Storage::disk('local')->path($estimate->pdf_path);

        // Build a request snapshot up-front so we can store it on the
        // estimate even if the provider rejects the call. This is the
        // single most valuable debugging artifact — it shows exactly what
        // we asked for, schema included.
        $requestSnapshot = [
            'instructions' => $agent->instructions(),
            'prompt' => $prompt,
            'schema' => (new ObjectType($agent->schema(new JsonSchemaTypeFactory)))->toArray(),
            'pdf_filename' => $estimate->pdf_original_filename,
            'ai_provider' => config('ai.default'),
        ];

        try {
            $response = $agent->prompt(
                $prompt,
                attachments: [Document::fromPath($absolutePdfPath)],
            );

            $data = $response->toArray();

            $this->persistSuccess($estimate, $data, $requestSnapshot, $response);
        } catch (Throwable $e) {
            $this->persistFailure($estimate, $e, $requestSnapshot);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $estimate = Estimate::withoutGlobalScopes()->find($this->estimateId);

        if ($estimate !== null && $estimate->status !== EstimateStatus::Failed) {
            $this->persistFailure($estimate, $exception, null);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $requestSnapshot
     */
    private function persistSuccess(
        Estimate $estimate,
        array $data,
        array $requestSnapshot,
        object $response,
    ): void {
        $rooms = is_array($data['rooms'] ?? null) ? $data['rooms'] : [];
        $errors = is_array($data['errors'] ?? null) ? array_values($data['errors']) : [];

        $debugLog = [
            'status' => 'success',
            'at' => now()->toIso8601String(),
            'request' => $requestSnapshot,
            'response' => [
                'text' => property_exists($response, 'text') ? $response->text : null,
                'structured' => $data,
            ],
        ];

        DB::transaction(function () use ($estimate, $data, $rooms, $errors, $debugLog): void {
            $estimate->rooms()->delete();

            $estimate->forceFill([
                'title' => is_string($data['title'] ?? null) ? $data['title'] : $estimate->title,
                'total_sqft' => is_numeric($data['total_sqft'] ?? null) ? (int) round($data['total_sqft']) : null,
                'agent_errors' => $errors,
                'debug_log' => $debugLog,
                'status' => EstimateStatus::Ready,
                // Asset rendering is its own job; mark it pending so the UI
                // shows a skeleton until the worker picks up the chained job.
                'floorplan_assets_status' => FloorplanAssetsStatus::Pending,
            ])->save();

            foreach ($rooms as $index => $room) {
                if (! is_array($room)) {
                    continue;
                }

                $sqft = is_numeric($room['sqft'] ?? null) ? (int) round($room['sqft']) : 0;
                $perimeter = is_numeric($room['perimeter'] ?? null) ? (int) round($room['perimeter']) : null;

                $estimate->rooms()->create([
                    'name' => is_string($room['name'] ?? null) ? $room['name'] : 'Room '.($index + 1),
                    'page' => is_numeric($room['page'] ?? null) ? max(1, (int) $room['page']) : 1,
                    'sqft' => max(0, $sqft),
                    'linear_feet' => $perimeter ?? LinearFeetFallback::approximate($sqft),
                    'position' => $index,
                ]);
            }
        });

        // Hand off to the renderer in a separate job so failures there don't
        // flip the main estimate to failed. afterCommit() avoids the database
        // queue race where a worker could pick up the row before this
        // transaction has been observed by other connections.
        ExtractEstimateFloorplanAssetsJob::dispatch($estimate->id)->afterCommit();
    }

    /**
     * @param  array<string, mixed>|null  $requestSnapshot
     */
    private function persistFailure(
        Estimate $estimate,
        Throwable $exception,
        ?array $requestSnapshot,
    ): void {
        $message = $this->fullErrorMessage($exception);

        $responseBody = null;
        $responseStatus = null;

        if ($exception instanceof RequestException && $exception->response !== null) {
            $responseStatus = $exception->response->status();
            $responseBody = $exception->response->body();
        }

        $debugLog = [
            'status' => 'failed',
            'at' => now()->toIso8601String(),
            'request' => $requestSnapshot,
            'exception' => [
                'class' => $exception::class,
                'message' => $exception->getMessage(),
            ],
            'response' => [
                'status' => $responseStatus,
                'body' => $responseBody,
            ],
        ];

        $estimate->forceFill([
            'status' => EstimateStatus::Failed,
            'agent_errors' => [$message],
            'debug_log' => $debugLog,
        ])->save();

        ActivityLogger::event(
            ActivityEvent::EstimateAgentFailed,
            metadata: [
                'estimate_id' => $estimate->id,
                'error' => $message,
            ],
            account: $estimate->account,
        );
    }

    /**
     * Reassemble the exception message without Laravel's 120-char body
     * truncation. {@see RequestException::prepareMessage()}
     * collapses the provider response down to a short summary, which hides
     * exactly the part operators need to debug schema and validation errors.
     */
    private function fullErrorMessage(Throwable $exception): string
    {
        if ($exception instanceof RequestException && $exception->response !== null) {
            $status = $exception->response->status();
            $body = $exception->response->body();

            return "HTTP request returned status code {$status}:\n{$body}";
        }

        return $exception->getMessage();
    }
}
