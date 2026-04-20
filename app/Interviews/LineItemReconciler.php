<?php

namespace App\Interviews;

use App\Models\Estimate;
use Illuminate\Support\Facades\DB;

class LineItemReconciler
{
    /**
     * @param  list<LineItemDraft>  $drafts
     */
    public function reconcile(Estimate $estimate, array $drafts): void
    {
        DB::transaction(function () use ($estimate, $drafts): void {
            $existing = $estimate->lineItems()->get()->keyBy('key');
            $draftKeys = collect($drafts)->pluck('key')->all();
            $position = 0;

            foreach ($drafts as $draft) {
                $row = $existing->get($draft->key);

                if ($row) {
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
                    $estimate->lineItems()->create([
                        'key' => $draft->key,
                        'label' => $draft->label,
                        'category' => $draft->category,
                        'kind' => $draft->kind,
                        'quantity' => $draft->quantity,
                        'unit' => $draft->unit,
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
