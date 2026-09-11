<?php

namespace App\Interviews;

use App\Enums\LineItemKind;
use App\Models\Estimate;
use App\Models\EstimateLineItem;

class LineItemPriceMemory
{
    /**
     * Look up the most recent labor unit price the account has used for each key.
     *
     * Materials deliberately excluded: per-unit labor for the same work (e.g.
     * install_lvp_labor, remove_carpet) stays stable job to job, while material
     * pricing varies. EstimateLineItem has no account_id, so we scope through the
     * parent estimate.
     *
     * @param  list<string>  $keys
     * @return array<string, float> key => last labor unit_price
     */
    public function lastLaborPricesFor(Estimate $estimate, array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        // Reduce to one row per key in SQL. An account's line-item history grows
        // without bound and this runs on every reconcile, so hydrating every
        // historical match and de-duplicating in PHP is not an option.
        $latestPerKey = EstimateLineItem::query()
            ->join('estimates', 'estimates.id', '=', 'estimate_line_items.estimate_id')
            ->where('estimates.account_id', $estimate->account_id)
            ->whereNull('estimates.deleted_at')
            ->where('estimate_line_items.estimate_id', '!=', $estimate->id)
            ->where('estimate_line_items.kind', LineItemKind::Labor->value)
            // A deprecated row was dropped from its quote and never reviewed
            // again, so its price is not evidence of what the account charges.
            ->whereNull('estimate_line_items.deprecated_at')
            ->whereNotNull('estimate_line_items.unit_price')
            ->whereIn('estimate_line_items.key', $keys)
            ->groupBy('estimate_line_items.key')
            ->selectRaw('max(estimate_line_items.id) as id');

        return EstimateLineItem::query()
            ->whereIn('id', $latestPerKey)
            ->get(['key', 'unit_price'])
            ->mapWithKeys(fn (EstimateLineItem $item): array => [$item->key => (float) $item->unit_price])
            ->all();
    }
}
