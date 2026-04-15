<?php

namespace App\Services;

/**
 * Approximates a room's perimeter (in linear feet) from its area when the
 * measuring tool or AI extraction did not emit an explicit perimeter value.
 *
 * The fallback treats the room as a square: side = sqrt(area), perimeter = 4 * side.
 * This is intentionally crude — it exists so every room has a non-null
 * `linear_feet` value for downstream estimating UI, not to replace a real
 * measurement.
 */
class LinearFeetFallback
{
    public static function approximate(int $sqft): int
    {
        if ($sqft <= 0) {
            return 0;
        }

        return (int) round(4 * sqrt($sqft));
    }
}
