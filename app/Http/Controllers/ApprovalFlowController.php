<?php

namespace App\Http\Controllers;

use App\Enums\QuoteStatus;
use App\Models\Estimate;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalFlowController extends Controller
{
    public function show(string $approval_token): Response
    {
        // This route is public, but a logged-in foreign user still has an
        // AccountContext set to *their* account. The BelongsToAccount scope
        // on Estimate / Project / Customer would otherwise filter out this
        // estimate's relations. Bypass the scope everywhere we touch.
        $estimate = Estimate::withoutGlobalScope('account')
            ->with([
                'account',
                'project' => fn ($q) => $q->withoutGlobalScope('account'),
                'customer' => fn ($q) => $q->withoutGlobalScope('account'),
            ])
            ->where('approval_token', $approval_token)
            ->where('quote_status', QuoteStatus::Sent)
            ->firstOrFail();

        return Inertia::render('quotes/approve', [
            'estimate' => [
                'title' => $estimate->title ?? $estimate->pdf_original_filename,
            ],
            'customer' => [
                'first_name' => $estimate->customer->first_name,
            ],
            'account_name' => $estimate->account->name,
            'steps' => [
                ['key' => 'sign', 'label' => 'Sign agreement', 'state' => 'coming_soon'],
                ['key' => 'deposit', 'label' => 'Pay deposit', 'state' => 'coming_soon'],
                ['key' => 'confirm', 'label' => 'Confirm scheduling', 'state' => 'coming_soon'],
            ],
        ]);
    }
}
