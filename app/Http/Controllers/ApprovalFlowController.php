<?php

namespace App\Http\Controllers;

use App\Enums\QuoteStatus;
use App\Models\Estimate;
use App\Services\QuoteSnapshot;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalFlowController extends Controller
{
    public function show(string $approval_token, QuoteSnapshot $snapshot): Response
    {
        $estimate = self::findEstimateByToken($approval_token);

        $signed = $estimate->hasSignedContract();
        $quote = $snapshot->build($estimate);
        $quoteHash = $snapshot->hash($quote);

        return Inertia::render('quotes/approve', [
            'estimate' => [
                'title' => $estimate->title ?? $estimate->pdf_original_filename,
            ],
            'customer' => [
                'first_name' => $estimate->customer->first_name,
            ],
            'account_name' => $estimate->account->name,
            'approval_token' => $approval_token,
            'quote' => $quote,
            'quote_hash' => $quoteHash,
            'is_locked' => $estimate->isLocked(),
            'contract' => [
                'signed' => $signed
                    ? [
                        'name' => $estimate->contract_signed_name,
                        'signed_at' => $estimate->contract_signed_at,
                    ]
                    : null,
            ],
            'steps' => [
                [
                    'key' => 'sign',
                    'label' => 'Sign the agreement',
                    'state' => $signed ? 'complete' : 'pending',
                ],
                ['key' => 'deposit', 'label' => 'Pay deposit', 'state' => 'coming_soon'],
                ['key' => 'confirm', 'label' => 'Confirm scheduling', 'state' => 'coming_soon'],
            ],
        ]);
    }

    public static function findEstimateByToken(string $approval_token): Estimate
    {
        // Public route: a logged-in foreign user still carries their own
        // AccountContext, so bypass the BelongsToAccount scope on every
        // relation we touch here.
        return Estimate::withoutGlobalScope('account')
            ->with([
                'account',
                'project' => fn ($q) => $q->withoutGlobalScope('account'),
                'customer' => fn ($q) => $q->withoutGlobalScope('account'),
            ])
            ->where('approval_token', $approval_token)
            ->where('quote_status', QuoteStatus::Sent)
            ->firstOrFail();
    }
}
