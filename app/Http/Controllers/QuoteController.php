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
use Illuminate\Database\Eloquent\Builder;
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

        abort_if($estimate->isLocked(), 409, 'Estimate is locked after customer acceptance.');
        abort_unless($estimate->status === EstimateStatus::Ready, 422);
        // A prefilled price is a suggestion carried over from an older
        // estimate, not a reviewed number. Treating it as unpriced keeps the
        // send guard doing what it was there for: forcing a human to sign off
        // on every figure the customer will see.
        abort_unless(
            $estimate->activeLineItems()
                ->where(function (Builder $query): void {
                    $query->whereNull('unit_price')
                        ->orWhere('price_prefilled', true);
                })
                ->doesntExist(),
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
