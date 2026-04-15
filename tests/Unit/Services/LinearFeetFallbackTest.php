<?php

use App\Services\LinearFeetFallback;

test('returns zero for zero or negative sqft', function () {
    expect(LinearFeetFallback::approximate(0))->toBe(0);
    expect(LinearFeetFallback::approximate(-5))->toBe(0);
});

test('approximates the perimeter as 4 * sqrt(sqft)', function () {
    // 100 sqft → 10x10 → 40 ft perimeter
    expect(LinearFeetFallback::approximate(100))->toBe(40);
    // 400 sqft → 20x20 → 80 ft
    expect(LinearFeetFallback::approximate(400))->toBe(80);
    // 2500 sqft → 50x50 → 200 ft
    expect(LinearFeetFallback::approximate(2500))->toBe(200);
});

test('rounds non-square values to the nearest foot', function () {
    // 150 sqft → sqrt ≈ 12.247 → 48.99 → 49
    expect(LinearFeetFallback::approximate(150))->toBe(49);
    // 1 sqft → sqrt = 1 → 4
    expect(LinearFeetFallback::approximate(1))->toBe(4);
});
