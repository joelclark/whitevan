<?php

namespace App\Http\Controllers;

use App\Contexts\AccountContext;
use App\Enums\ActivityEvent;
use App\Enums\EstimateStatus;
use App\Enums\QuoteStatus;
use App\Mail\QuoteSentToCustomer;
use App\Models\Estimate;
use App\Services\ActivityLogger;
use App\Services\ProjectEventLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class QuoteController extends Controller
{
    public function send(
        Request $request,
        Estimate $estimate,
        AccountContext $accountContext,
    ): RedirectResponse {
        $account = $accountContext->get();
        abort_if($account === null, 403);

        abort_unless($estimate->status === EstimateStatus::Ready, 422);
        abort_unless(
            $estimate->activeLineItems()->whereNull('unit_price')->doesntExist(),
            422,
        );

        $estimate->update([
            'quote_status' => QuoteStatus::Sent,
            'quote_token' => $estimate->quote_token ?? Str::ulid()->toBase32(),
            'quote_sent_at' => now(),
            'approval_token' => $estimate->approval_token ?? Str::ulid()->toBase32(),
        ]);

        $estimate->recordProjectActivity();

        ActivityLogger::event(
            ActivityEvent::EstimateQuoteSent,
            metadata: ['estimate_id' => $estimate->id],
            account: $account,
            user: $request->user(),
        );

        ProjectEventLogger::record(
            $estimate,
            ActivityEvent::EstimateQuoteSent,
            user: $request->user(),
        );

        $customerEmail = $estimate->customer?->email;

        if ($customerEmail === null) {
            return back()->with('status', 'quote-saved-no-email');
        }

        Mail::to($customerEmail)->queue(new QuoteSentToCustomer($estimate));

        return back()->with('status', 'quote-sent');
    }
}
