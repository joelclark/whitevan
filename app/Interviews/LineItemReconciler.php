<?php

namespace App\Interviews;

use App\Models\Estimate;
use Illuminate\Support\Facades\DB;

class LineItemReconciler
{
    public function __construct(private LineItemPriceMemory $priceMemory) {}

    /**
     * @param  list<LineItemDraft>  $drafts
     */
    public function reconcile(Estimate $estimate, array $drafts): void
    {
        DB::transaction(function () use ($estimate, $drafts): void {
            $existing = $estimate->lineItems()->get()->keyBy('key');
            $draftKeys = collect($drafts)->pluck('key')->all();
            $priceMap = $this->priceMemory->lastLaborPricesFor($estimate, $draftKeys);
            $position = 0;

            foreach ($drafts as $draft) {
                $row = $existing->get($draft->key);

                if ($row) {
                    // unit_price and price_prefilled are deliberately omitted:
                    // a user-entered (or previously prefilled) price survives
                    // re-emission untouched.
                    $row->update([
                        'label' => $draft->label,
                        'category' => $draft->category,
                        'kind' => $draft->kind,
                        'quantity' => $draft->quantity,
                        'unit' => $draft->unit,
                        'notes' => $draft->notes,
                        'position' => $position,
                        'deprecated_at' => null,
                    ]);
                } else {
                    $prefill = $priceMap[$draft->key] ?? null;

                    $estimate->lineItems()->create([
                        'key' => $draft->key,
                        'label' => $draft->label,
                        'category' => $draft->category,
                        'kind' => $draft->kind,
                        'quantity' => $draft->quantity,
                        'unit' => $draft->unit,
                        'unit_price' => $prefill,
                        'price_prefilled' => $prefill !== null,
                        'notes' => $draft->notes,
                        'position' => $position,
                    ]);
                }

                $position++;
            }

            $estimate->lineItems()
                ->whereNull('deprecated_at')
                ->whereNotIn('key', $draftKeys)
                ->update(['deprecated_at' => now()]);
        });
    }
}
