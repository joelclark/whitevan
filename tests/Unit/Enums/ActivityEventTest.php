<?php

use App\Enums\ActivityEvent;

test('every case has a non-empty label', function () {
    foreach (ActivityEvent::cases() as $case) {
        expect($case->label())->not->toBe('');
    }
});

test('every case value follows the domain.action shape', function () {
    foreach (ActivityEvent::cases() as $case) {
        expect($case->value)->toMatch('/^[a-z]+(\.[a-z_]+)+$/');
    }
});

test('every case defines isCustomerVisible', function () {
    // The match() inside isCustomerVisible() has no default arm, so a newly
    // added case without an entry throws UnhandledMatchError here. Guardrail
    // that forces authors to make an explicit visibility decision per event.
    foreach (ActivityEvent::cases() as $case) {
        expect(fn () => $case->isCustomerVisible())->not->toThrow(Throwable::class);
    }
});
