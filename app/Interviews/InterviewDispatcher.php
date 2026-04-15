<?php

namespace App\Interviews;

use App\Enums\Trade;
use App\Models\Estimate;
use App\Trades\Flooring\Interview\FlooringInterview;

final class InterviewDispatcher
{
    public static function for(Estimate $estimate): TradeInterview
    {
        return match ($estimate->trade) {
            Trade::Flooring => app(FlooringInterview::class),
        };
    }
}
