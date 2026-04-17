<?php

namespace App\Http\Controllers;

use App\Contexts\AccountContext;
use App\Enums\ActivityEvent;
use App\Enums\EstimateStatus;
use App\Enums\FloorplanAssetsStatus;
use App\Enums\Trade;
use App\Http\Requests\EstimateStoreRequest;
use App\Http\Requests\EstimateUpdateRequest;
use App\Interviews\InterviewDispatcher;
use App\Interviews\LineItemEmitterDispatcher;
use App\Interviews\LineItemReconciler;
use App\Jobs\ProcessEstimatePdfJob;
use App\Models\Estimate;
use App\Models\Project;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EstimateController extends Controller
{
    public function index(Request $request, AccountContext $accountContext): Response
    {
        $account = $accountContext->get();
        abort_if($account === null, 403);

        $search = trim((string) $request->query('search', ''));

        $estimates = Estimate::query()
            ->with([
                'project:id,name,customer_id',
                'project.customer:id,first_name,last_name,company',
            ])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where(function (Builder $q) use ($like): void {
                    $q->whereLike('title', $like, caseSensitive: false)
                        ->orWhereHas('project', function (Builder $pq) use ($like): void {
                            // Group the OR chain so the whereHas's auto-injected
                            // foreign-key constraint remains an AND. Same trick
                            // as the customer branch below.
                            $pq->where(function (Builder $inner) use ($like): void {
                                $inner->whereLike('name', $like, caseSensitive: false);
                            });
                        })
                        ->orWhereHas('project.customer', function (Builder $cq) use ($like): void {
                            $cq->where(function (Builder $inner) use ($like): void {
                                $inner->whereLike('first_name', $like, caseSensitive: false)
                                    ->orWhereLike('last_name', $like, caseSensitive: false)
                                    ->orWhereLike('company', $like, caseSensitive: false);
                            });
                        });
                });
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('estimates/index', [
            'estimates' => $estimates,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function store(
        EstimateStoreRequest $request,
        Project $project,
        AccountContext $accountContext,
    ): RedirectResponse {
        $account = $accountContext->get();
        abort_if($account === null, 403);

        $file = $request->file('pdf');
        $path = $file->storeAs(
            'estimate-pdfs',
            Str::ulid().'.pdf',
            'local',
        );

        $estimate = Estimate::create([
            'project_id' => $project->id,
            // Flooring is the only trade today. When trade selection lands in
            // the upload form, read it from $request instead of hard-coding.
            'trade' => Trade::Flooring,
            'pdf_path' => $path,
            'pdf_original_filename' => $file->getClientOriginalName(),
            'status' => EstimateStatus::Processing,
            'interview_answers' => [],
            'agent_errors' => [],
        ]);

        ProcessEstimatePdfJob::dispatch($estimate->id);

        ActivityLogger::event(
            ActivityEvent::EstimateCreated,
            metadata: [
                'estimate_id' => $estimate->id,
                'project_id' => $project->id,
                'customer_id' => $project->customer_id,
                'pdf_original_filename' => $estimate->pdf_original_filename,
            ],
            account: $account,
            user: $request->user(),
        );

        return redirect()->route('estimates.edit', $estimate);
    }

    public function edit(Estimate $estimate): Response
    {
        $estimate->load(['customer', 'project', 'rooms', 'floorplanPages', 'activeLineItems']);

        // Normalize interview_answers for Inertia: AsArrayObject flattens
        // empty inner maps to [], but the frontend type expects objects.
        // Cast inner maps to stdClass at the JSON boundary so empties
        // serialize as {} instead of [].
        $serialized = $estimate->toArray();
        $raw = $estimate->interview_answers;
        $rooms = $raw['rooms'] ?? [];
        $projectWide = $raw['project_wide'] ?? [];
        $serialized['interview_answers'] = [
            'rooms' => (object) ($rooms instanceof \ArrayObject ? $rooms->getArrayCopy() : (array) $rooms),
            'project_wide' => (object) ($projectWide instanceof \ArrayObject ? $projectWide->getArrayCopy() : (array) $projectWide),
        ];

        $interviewProps = null;
        if ($estimate->status === EstimateStatus::Ready) {
            $interview = InterviewDispatcher::for($estimate);
            $next = $interview->nextQuestionFor($estimate);
            $isComplete = $next === null;
            $interviewProps = [
                'next_question' => $next?->toArray(),
                'is_complete' => $isComplete,
                'catalog' => $interview->catalog(),
            ];

            // Backfill: estimates completed before line-item emission was
            // deployed have no rows yet. Emit on first visit so they
            // don't need a manual retry.
            if ($isComplete && $estimate->activeLineItems->isEmpty()) {
                $drafts = LineItemEmitterDispatcher::for($estimate)->emit($estimate);
                app(LineItemReconciler::class)->reconcile($estimate, $drafts);
                $estimate->load('activeLineItems');
            }
        }

        $floorplanPages = $estimate->floorplanPages
            ->map(fn ($page) => [
                'page' => $page->page,
                'width' => $page->width,
                'height' => $page->height,
                'url' => route('estimates.floorplan-page', ['estimate' => $estimate->id, 'page' => $page->page]),
            ])
            ->values()
            ->all();

        $lineItems = $estimate->activeLineItems
            ->map(fn ($li) => [
                'id' => $li->id,
                'key' => $li->key,
                'label' => $li->label,
                'category' => $li->category->value,
                'category_label' => $li->category->label(),
                'quantity' => (float) $li->quantity,
                'unit' => $li->unit->abbreviation(),
                'unit_price' => $li->unit_price !== null ? (float) $li->unit_price : null,
                'notes' => $li->notes,
            ])
            ->values()
            ->all();

        return Inertia::render('estimates/edit', [
            'estimate' => $serialized,
            'interview' => $interviewProps,
            'floorplan_pages' => $floorplanPages,
            'line_items' => $lineItems,
        ]);
    }

    public function update(
        EstimateUpdateRequest $request,
        Estimate $estimate,
        AccountContext $accountContext,
    ): RedirectResponse {
        $account = $accountContext->get();
        abort_if($account === null, 403);

        $validated = $request->validated();

        $estimate->fill([
            'title' => $validated['title'] ?? $estimate->title,
        ]);

        if ($estimate->isDirty()) {
            $estimate->save();

            ActivityLogger::event(
                ActivityEvent::EstimateUpdated,
                metadata: [
                    'estimate_id' => $estimate->id,
                    'project_id' => $estimate->project_id,
                ],
                account: $account,
                user: $request->user(),
            );
        }

        return back()->with('status', 'estimate-updated');
    }

    public function retry(
        Request $request,
        Estimate $estimate,
        AccountContext $accountContext,
    ): RedirectResponse {
        $account = $accountContext->get();
        abort_if($account === null, 403);

        // Block only while a job is already in flight — otherwise two
        // parallel runs would race each other when writing rooms. Ready
        // and Failed are both eligible. Ready gets clobbered (rooms,
        // title, total_sqft are rewritten from the next response); the
        // UI confirms before sending that through.
        abort_if($estimate->status === EstimateStatus::Processing, 409);

        // Stale floorplan PNGs from the previous run would otherwise show in
        // the gallery during the gap between retry and the new render job
        // completing. Wipe rows + files up-front so the UI shows a clean
        // skeleton state.
        $stalePages = $estimate->floorplanPages()->get();
        if ($stalePages->isNotEmpty()) {
            Storage::disk('local')->delete($stalePages->pluck('image_path')->all());
            $estimate->floorplanPages()->delete();
        }

        $estimate->lineItems()->delete();

        // Retry regenerates rooms with new ids, so any stored room-scoped
        // answers in interview_answers would orphan. Reset to a clean
        // nested shape so the interview restarts from scratch.
        $debugLog = is_array($estimate->debug_log) ? $estimate->debug_log : [];
        unset($debugLog['floorplan']);

        $estimate->forceFill([
            'status' => EstimateStatus::Processing,
            'floorplan_assets_status' => FloorplanAssetsStatus::Pending,
            'agent_errors' => [],
            'debug_log' => $debugLog === [] ? null : $debugLog,
            'interview_answers' => [
                'rooms' => new \ArrayObject,
                'project_wide' => new \ArrayObject,
            ],
        ])->save();

        ProcessEstimatePdfJob::dispatch($estimate->id);

        return back();
    }

    public function destroy(
        Request $request,
        Estimate $estimate,
        AccountContext $accountContext,
    ): RedirectResponse {
        $account = $accountContext->get();
        abort_if($account === null, 403);

        $estimateId = $estimate->id;
        $projectId = $estimate->project_id;

        $estimate->delete();

        ActivityLogger::event(
            ActivityEvent::EstimateDeleted,
            metadata: [
                'estimate_id' => $estimateId,
                'project_id' => $projectId,
            ],
            account: $account,
            user: $request->user(),
        );

        return redirect()
            ->route('estimates.index')
            ->with('status', 'estimate-deleted');
    }

    public function updateLineItem(
        Request $request,
        Estimate $estimate,
        int $lineItem,
    ): RedirectResponse {
        $item = $estimate->activeLineItems()->findOrFail($lineItem);

        $validated = $request->validate([
            'unit_price' => ['present', 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        $item->update([
            'unit_price' => $validated['unit_price'],
        ]);

        return back();
    }

    public function pdf(Estimate $estimate): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($estimate->pdf_path), 404);

        return Storage::disk('local')->response(
            $estimate->pdf_path,
            $estimate->pdf_original_filename,
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function floorplanPage(Estimate $estimate, int $page): StreamedResponse
    {
        // Always look up by the relationship — never accept a path from the
        // request. Route-model binding has already enforced account scoping
        // on $estimate via the BelongsToAccount global scope.
        $row = $estimate->floorplanPages()->where('page', $page)->firstOrFail();

        abort_unless(Storage::disk('local')->exists($row->image_path), 404);

        return Storage::disk('local')->response(
            $row->image_path,
            "estimate-{$estimate->id}-page-{$page}.png",
            [
                'Content-Type' => 'image/png',
                // Images are immutable per (estimate, page) — a re-render
                // wipes the row entirely. Cache aggressively so the 2-second
                // poll loop doesn't re-fetch every PNG on each tick.
                'Cache-Control' => 'private, max-age=3600',
            ],
        );
    }
}
