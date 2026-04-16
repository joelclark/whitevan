<?php

namespace App\Interviews;

use App\Models\Estimate;

interface TradeLineItemEmitter
{
    /** @return list<LineItemDraft> */
    public function emit(Estimate $estimate): array;
}
