<?php

namespace App\Http\Controllers;

use App\Contexts\AccountContext;
use App\Enums\ActivityEvent;
use App\Enums\EstimateStatus;
use App\Enums\QuoteStatus;
use App\Models\Estimate;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        ]);

        $estimate->recordProjectActivity();

        ActivityLogger::event(
            ActivityEvent::EstimateQuoteSent,
            metadata: ['estimate_id' => $estimate->id],
            account: $account,
            user: $request->user(),
        );

        return back()->with('status', 'quote-sent');
    }

    public function show(string $token): Response
    {
        $estimate = Estimate::withoutGlobalScope('account')
            ->where('quote_token', $token)
            ->where('quote_status', QuoteStatus::Sent)
            ->firstOrFail();

        $estimate->load(['customer', 'rooms', 'activeLineItems', 'floorplanPages', 'account']);

        $hasChanged = $estimate->hasChangedSinceCustomerViewed();

        // Only update the customer-viewed timestamp when there is no
        // authenticated user. Internal users previewing the quote via
        // "View Quote" should not reset the change-detection marker.
        if (auth()->guest()) {
            $estimate->update(['quote_customer_viewed_at' => now()]);
            $estimate->recordProjectActivity();

            ActivityLogger::event(
                ActivityEvent::EstimateQuoteViewed,
                metadata: ['estimate_id' => $estimate->id],
                account: $estimate->account,
            );
        }

        $floorplanPages = $estimate->floorplanPages
            ->map(fn ($page) => [
                'page' => $page->page,
                'width' => $page->width,
                'height' => $page->height,
                'url' => route('quotes.floorplan-page', ['token' => $estimate->quote_token, 'page' => $page->page]),
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

        return Inertia::render('quotes/show', [
            'estimate' => [
                'title' => $estimate->title ?? $estimate->pdf_original_filename,
                'total_sqft' => $estimate->total_sqft,
                'trade' => $estimate->trade->value,
                'quote_sent_at' => $estimate->quote_sent_at?->toIso8601String(),
            ],
            'customer' => [
                'first_name' => $estimate->customer->first_name,
                'last_name' => $estimate->customer->last_name,
                'company' => $estimate->customer->company,
            ],
            'account_name' => $estimate->account->name,
            'rooms' => $estimate->rooms->map(fn ($room) => [
                'id' => $room->id,
                'name' => $room->name,
                'sqft' => $room->sqft,
                'linear_feet' => $room->linear_feet,
                'page' => $room->page,
            ])->values()->all(),
            'floorplan_pages' => $floorplanPages,
            'line_items' => $lineItems,
            'has_changed' => $hasChanged,
        ]);
    }

    public function floorplanPage(string $token, int $page): StreamedResponse
    {
        $estimate = Estimate::withoutGlobalScope('account')
            ->where('quote_token', $token)
            ->where('quote_status', QuoteStatus::Sent)
            ->firstOrFail();

        $row = $estimate->floorplanPages()->where('page', $page)->firstOrFail();

        abort_unless(Storage::disk(config('estimates.disk'))->exists($row->image_path), 404);

        return Storage::disk(config('estimates.disk'))->response(
            $row->image_path,
            "quote-page-{$page}.png",
            [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'private, max-age=3600',
            ],
        );
    }
}
