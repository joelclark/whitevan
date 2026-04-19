<?php

namespace App\Http\Controllers;

use App\Contexts\AccountContext;
use App\Enums\ActivityEvent;
use App\Enums\EstimateStatus;
use App\Http\Requests\EstimateInterviewAnswerRequest;
use App\Interviews\InterviewDispatcher;
use App\Interviews\LineItemEmitterDispatcher;
use App\Interviews\LineItemReconciler;
use App\Models\Estimate;
use App\Services\ActivityLogger;
use App\Services\ProjectEventLogger;
use Illuminate\Http\RedirectResponse;

class EstimateInterviewController extends Controller
{
    public function store(
        EstimateInterviewAnswerRequest $request,
        Estimate $estimate,
        AccountContext $accountContext,
    ): RedirectResponse {
        $account = $accountContext->get();
        abort_if($account === null, 403);

        // Interview is only meaningful once extraction has produced rooms.
        // Mirrors the 409 convention used by retry().
        abort_if($estimate->status !== EstimateStatus::Ready, 409);

        $validated = $request->validated();

        $interview = InterviewDispatcher::for($estimate);

        $wasCompleteBefore = $interview->isComplete($estimate);

        $interview->recordAnswerFor(
            $estimate,
            $validated['question_key'],
            isset($validated['room_id']) ? (int) $validated['room_id'] : null,
            $validated['value'],
        );

        $estimate->save();
        $estimate->recordProjectActivity();

        $isNowComplete = $interview->isComplete($estimate);

        if ($isNowComplete) {
            $drafts = LineItemEmitterDispatcher::for($estimate)->emit($estimate);
            app(LineItemReconciler::class)->reconcile($estimate, $drafts);
        }

        if (! $wasCompleteBefore && $isNowComplete) {
            ActivityLogger::event(
                ActivityEvent::EstimateInterviewCompleted,
                metadata: [
                    'estimate_id' => $estimate->id,
                    'project_id' => $estimate->project_id,
                    'line_items_count' => count($drafts),
                ],
                account: $account,
                user: $request->user(),
            );

            ProjectEventLogger::record(
                $estimate,
                ActivityEvent::EstimateInterviewCompleted,
                user: $request->user(),
                metadata: ['line_items_count' => count($drafts)],
            );
        }

        return back();
    }
}
