<?php

namespace App\Interviews;

use App\Enums\Trade;
use App\Models\Estimate;
use App\Trades\Flooring\LineItems\FlooringLineItemEmitter;

final class LineItemEmitterDispatcher
{
    public static function for(Estimate $estimate): TradeLineItemEmitter
    {
        return match ($estimate->trade) {
            Trade::Flooring => app(FlooringLineItemEmitter::class),
        };
    }
}
