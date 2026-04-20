<?php

namespace App\Http\Controllers;

use App\Enums\ActivityEvent;
use App\Enums\ActorType;
use App\Http\Requests\ContractSignRequest;
use App\Models\Estimate;
use App\Models\ProjectEvent;
use App\Services\ActivityLogger;
use App\Services\ContractMarkdown;
use App\Services\ContractResolver;
use App\Services\ProjectEventLogger;
use App\Services\QuoteSnapshot;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ContractSigningController extends Controller
{
    public function show(
        Request $request,
        string $approval_token,
        ContractResolver $resolver,
        ContractMarkdown $markdown,
        QuoteSnapshot $snapshot,
    ): Response {
        $estimate = ApprovalFlowController::findEstimateByToken($approval_token);

        $signed = $estimate->hasSignedContract();

        // The "customer viewed the contract" event fires when they actually
        // open the contract page — not the lightweight overview — so the
        // semantics match the enum name. Idempotent per estimate.
        if (! $signed) {
            $this->recordFirstViewIfNew($estimate, $request);
        }

        $source = $signed
            ? ($estimate->contract_body_snapshot ?? '')
            : $resolver->bodyForAccount($estimate->account);

        $quote = $snapshot->build($estimate);
        $quoteHash = $snapshot->hash($quote);

        return Inertia::render('quotes/sign', [
            'estimate' => [
                'title' => $estimate->title ?? $estimate->pdf_original_filename,
            ],
            'account_name' => $estimate->account->name,
            'approval_token' => $approval_token,
            'quote' => $quote,
            'quote_hash' => $quoteHash,
            'is_locked' => $estimate->isLocked(),
            'contract' => [
                'body_html' => $markdown->toHtml($source),
                'signed' => $signed
                    ? [
                        'name' => $estimate->contract_signed_name,
                        'signed_at' => $estimate->contract_signed_at,
                    ]
                    : null,
            ],
        ]);
    }

    public function store(
        ContractSignRequest $request,
        string $approval_token,
        ContractResolver $resolver,
        QuoteSnapshot $quoteSnapshot,
    ): RedirectResponse {
        $estimate = ApprovalFlowController::findEstimateByToken($approval_token);

        // Fast-path guard for obvious already-signed repeats. The real
        // protection against concurrent double-signs is the conditional
        // UPDATE below — this check just saves a pointless write round-trip
        // when a single client re-submits the form.
        abort_if($estimate->hasSignedContract(), 404);
        abort_if($estimate->isLocked(), 409, 'Estimate is locked.');

        $validated = $request->validated();

        // Change detection: the hash the customer saw on the sign page must
        // match the hash of the live quote now. If the contractor edited the
        // quote or an admin changed deposit percentages between render and
        // submit, reject with a flash so the customer re-reviews the updated
        // figures before committing.
        $currentQuote = $quoteSnapshot->build($estimate);
        $currentHash = $quoteSnapshot->hash($currentQuote);

        if (! hash_equals($currentHash, (string) $validated['quote_hash'])) {
            ActivityLogger::event(
                ActivityEvent::EstimateQuoteChangeDetectedAtSigning,
                metadata: [
                    'estimate_id' => $estimate->id,
                    'submitted_hash' => $validated['quote_hash'],
                    'current_hash' => $currentHash,
                ],
                account: $estimate->account,
            );

            return redirect()
                ->route('approve.show', ['approval_token' => $approval_token])
                ->with('quote_changed', true);
        }

        $snapshot = $resolver->bodyForAccount($estimate->account);
        $signedName = $validated['name'];
        $signedIp = $request->ip();
        $signedUserAgent = mb_substr((string) $request->userAgent(), 0, 500);

        // Signing is single-shot. The `whereNull('contract_signed_at')`
        // predicate turns this into an atomic claim: exactly one concurrent
        // writer updates 1 row; every other writer updates 0 and the caller
        // 404s. The event row is only written inside the same transaction
        // when we actually claimed the signature.
        //
        // NOTE: Auto-lock on sign is temporarily disabled. The `locked_at`
        // column, the isLocked() guards on updateLineItem / interview store /
        // quote send, and the frontend read-only rendering all still exist —
        // they just don't fire automatically. To re-enable, add
        // `'locked_at' => $now` back to the update array below.
        $claimed = DB::transaction(function () use ($estimate, $snapshot, $signedName, $signedIp, $signedUserAgent, $currentQuote) {
            $affected = Estimate::withoutGlobalScope('account')
                ->whereKey($estimate->id)
                ->whereNull('contract_signed_at')
                ->update([
                    'contract_body_snapshot' => $snapshot,
                    'contract_signed_at' => now(),
                    'contract_signed_name' => $signedName,
                    'contract_signed_ip' => $signedIp,
                    'contract_signed_user_agent' => $signedUserAgent,
                ]);

            if ($affected === 0) {
                return false;
            }

            ProjectEventLogger::record(
                $estimate,
                ActivityEvent::QuoteContractSigned,
                metadata: [
                    'name' => $signedName,
                    'ip' => $signedIp,
                    'user_agent' => $signedUserAgent,
                ],
                actorType: ActorType::Customer,
            );

            ActivityLogger::event(
                ActivityEvent::EstimateQuoteAccepted,
                metadata: [
                    'estimate_id' => $estimate->id,
                    'grand_total' => $currentQuote['grand_total'],
                    'material_subtotal' => $currentQuote['material_subtotal'],
                    'labor_subtotal' => $currentQuote['labor_subtotal'],
                    'deposit_total' => $currentQuote['deposit_total'],
                ],
                account: $estimate->account,
            );

            return true;
        });

        abort_if(! $claimed, 404);

        $estimate->recordProjectActivity();

        return redirect()
            ->route('approve.show', ['approval_token' => $approval_token])
            ->with('status', 'contract-signed');
    }

    private function recordFirstViewIfNew(Estimate $estimate, Request $request): void
    {
        // Cheap fast-path for the common non-racing case — skips a pointless
        // write round-trip when we already know the event exists. The real
        // "first view only" invariant is enforced by the partial unique
        // index `project_events_contract_viewed_unique`, which catches the
        // case where two concurrent GETs both pass this check.
        $alreadyViewed = ProjectEvent::withoutGlobalScopes()
            ->where('estimate_id', $estimate->id)
            ->where('event', ActivityEvent::QuoteContractViewed)
            ->exists();

        if ($alreadyViewed) {
            return;
        }

        try {
            ProjectEventLogger::record(
                $estimate,
                ActivityEvent::QuoteContractViewed,
                metadata: [
                    'ip' => $request->ip(),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                ],
                actorType: ActorType::Customer,
            );
        } catch (UniqueConstraintViolationException) {
            // Another concurrent request won the race and wrote the row
            // first. The invariant still holds — nothing to do.
        }
    }
}
