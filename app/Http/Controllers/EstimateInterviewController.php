<?php

namespace App\Http\Controllers;

use App\Contexts\AccountContext;
use App\Enums\ActivityEvent;
use App\Enums\EstimateStatus;
use App\Http\Requests\EstimateInterviewAnswerRequest;
use App\Interviews\InterviewDispatcher;
use App\Models\Estimate;
use App\Services\ActivityLogger;
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

        if (! $wasCompleteBefore && $interview->isComplete($estimate)) {
            ActivityLogger::event(
                ActivityEvent::EstimateInterviewCompleted,
                metadata: [
                    'estimate_id' => $estimate->id,
                    'customer_id' => $estimate->customer_id,
                ],
                account: $account,
                user: $request->user(),
            );
        }

        return back();
    }
}
