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

        return EstimateLineItem::query()
            ->join('estimates', 'estimates.id', '=', 'estimate_line_items.estimate_id')
            ->where('estimates.account_id', $estimate->account_id)
            ->whereNull('estimates.deleted_at')
            ->where('estimate_line_items.estimate_id', '!=', $estimate->id)
            ->where('estimate_line_items.kind', LineItemKind::Labor->value)
            ->whereNotNull('estimate_line_items.unit_price')
            ->whereIn('estimate_line_items.key', $keys)
            ->orderByDesc('estimate_line_items.id')
            ->get(['estimate_line_items.key', 'estimate_line_items.unit_price'])
            ->unique('key')
            ->mapWithKeys(fn (EstimateLineItem $item): array => [$item->key => (float) $item->unit_price])
            ->all();
    }
}
