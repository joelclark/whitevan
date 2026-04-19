<?php

use App\Enums\ActorType;

test('every case value is a lowercase snake-case token', function () {
    foreach (ActorType::cases() as $case) {
        expect($case->value)->toMatch('/^[a-z]+(_[a-z]+)*$/');
    }
});
