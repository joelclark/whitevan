<?php

namespace App\Http\Controllers;

use App\Contexts\AccountContext;
use App\Enums\ActivityEvent;
use App\Enums\EstimateStatus;
use App\Http\Requests\EstimateStoreRequest;
use App\Http\Requests\EstimateUpdateRequest;
use App\Jobs\ProcessEstimatePdfJob;
use App\Models\Customer;
use App\Models\Estimate;
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
            ->with(['customer:id,first_name,last_name,company'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where(function (Builder $q) use ($like): void {
                    $q->whereLike('title', $like, caseSensitive: false)
                        ->orWhereHas('customer', function (Builder $cq) use ($like): void {
                            // Group the OR chain so the whereHas's auto-injected
                            // foreign-key constraint remains an AND. Otherwise
                            // the first column's orWhere bubbles up and every
                            // row matches on the FK side of the boolean.
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
        Customer $customer,
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
            'customer_id' => $customer->id,
            'pdf_path' => $path,
            'pdf_original_filename' => $file->getClientOriginalName(),
            'status' => EstimateStatus::Processing,
            'interview_answers' => [],
            'line_item_prices' => [],
            'agent_errors' => [],
        ]);

        ProcessEstimatePdfJob::dispatch($estimate->id);

        ActivityLogger::event(
            ActivityEvent::EstimateCreated,
            metadata: [
                'estimate_id' => $estimate->id,
                'customer_id' => $customer->id,
                'pdf_original_filename' => $estimate->pdf_original_filename,
            ],
            account: $account,
            user: $request->user(),
        );

        return redirect()->route('estimates.edit', $estimate);
    }

    public function edit(Estimate $estimate): Response
    {
        $estimate->load(['customer', 'rooms']);

        return Inertia::render('estimates/edit', [
            'estimate' => $estimate,
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
            'interview_answers' => $validated['interview_answers'] ?? $estimate->interview_answers->getArrayCopy(),
            'line_item_prices' => $validated['line_item_prices'] ?? $estimate->line_item_prices->getArrayCopy(),
        ]);

        if ($estimate->isDirty()) {
            $estimate->save();

            ActivityLogger::event(
                ActivityEvent::EstimateUpdated,
                metadata: [
                    'estimate_id' => $estimate->id,
                    'customer_id' => $estimate->customer_id,
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

        $estimate->forceFill([
            'status' => EstimateStatus::Processing,
            'agent_errors' => [],
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
        $customerId = $estimate->customer_id;

        $estimate->delete();

        ActivityLogger::event(
            ActivityEvent::EstimateDeleted,
            metadata: [
                'estimate_id' => $estimateId,
                'customer_id' => $customerId,
            ],
            account: $account,
            user: $request->user(),
        );

        return redirect()
            ->route('estimates.index')
            ->with('status', 'estimate-deleted');
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
}
