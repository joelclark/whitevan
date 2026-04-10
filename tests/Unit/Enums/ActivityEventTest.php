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
