<?php

use App\Interviews\Phase;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\EstimateRoom;
use App\Trades\Flooring\Interview\FlooringInterview;
use App\Trades\Flooring\Interview\Questions\ProjectWide\BaseboardsQuestion;
use App\Trades\Flooring\Interview\Questions\ProjectWide\CustomerTypeQuestion;
use App\Trades\Flooring\Interview\Questions\ProjectWide\DemoHaulAwayQuestion;
use App\Trades\Flooring\Interview\Questions\ProjectWide\DoorUndercutsQuestion;
use App\Trades\Flooring\Interview\Questions\ProjectWide\QuarterRoundQuestion;
use App\Trades\Flooring\Interview\Questions\ProjectWide\ToiletPullsQuestion;
use App\Trades\Flooring\Interview\Questions\ProjectWide\TransitionsQuestion;
use App\Trades\Flooring\Interview\Questions\Room\ExistingQuestion;
use App\Trades\Flooring\Interview\Questions\Room\FurnitureQuestion;
use App\Trades\Flooring\Interview\Questions\Room\HeavyCountQuestion;
use App\Trades\Flooring\Interview\Questions\Room\MaterialQuestion;
use App\Trades\Flooring\Interview\Questions\Room\SubfloorQuestion;
use Illuminate\Validation\ValidationException;

function estimateWithRooms(int $count, ?array $positions = null): Estimate
{
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create();

    $positions ??= $count > 0 ? range(1, $count) : [];

    foreach ($positions as $pos) {
        EstimateRoom::factory()->create([
            'estimate_id' => $estimate->id,
            'position' => $pos,
            'name' => "Room {$pos}",
        ]);
    }

    return $estimate->refresh();
}

test('walks rooms in ascending position order', function () {
    $estimate = estimateWithRooms(3, [3, 1, 2]);
    $interview = app(FlooringInterview::class);

    $pending = $interview->nextQuestionFor($estimate);

    expect($pending)->not->toBeNull();
    expect($pending->phase)->toBe(Phase::Room);
    expect($pending->room->position)->toBe(1);
    expect($pending->question->key())->toBe('material');
    expect($pending->roomIndex)->toBe(0);
    expect($pending->totalRooms)->toBe(3);
});

test('asks room questions in order: material, existing, subfloor, furniture', function () {
    $estimate = estimateWithRooms(1);
    $interview = app(FlooringInterview::class);
    $room = $estimate->rooms->first();

    $expected = ['material', 'existing', 'subfloor', 'furniture'];

    foreach ($expected as $key) {
        $pending = $interview->nextQuestionFor($estimate);
        expect($pending->question->key())->toBe($key);

        $interview->recordAnswerFor($estimate, $key, $room->id, match ($key) {
            'material' => 'lvp',
            'existing' => 'carpet',
            'subfloor' => 'none',
            'furniture' => 'empty',
        });
    }

    // Now should fall through to the project-wide phase.
    $pending = $interview->nextQuestionFor($estimate);
    expect($pending->phase)->toBe(Phase::ProjectWide);
});

test('heavy_count is skipped when furniture is not heavy', function () {
    $estimate = estimateWithRooms(1);
    $interview = app(FlooringInterview::class);
    $room = $estimate->rooms->first();

    foreach (['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty'] as $k => $v) {
        $interview->recordAnswerFor($estimate, $k, $room->id, $v);
    }

    // heavy_count should not be asked; project-wide phase should begin.
    $pending = $interview->nextQuestionFor($estimate);
    expect($pending->phase)->toBe(Phase::ProjectWide);
    expect($pending->question->key())->toBe('customer_type');
});

test('heavy_count is asked when furniture is heavy', function () {
    $estimate = estimateWithRooms(1);
    $interview = app(FlooringInterview::class);
    $room = $estimate->rooms->first();

    foreach (['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'heavy'] as $k => $v) {
        $interview->recordAnswerFor($estimate, $k, $room->id, $v);
    }

    $pending = $interview->nextQuestionFor($estimate);
    expect($pending->question->key())->toBe('heavy_count');
    expect($pending->phase)->toBe(Phase::Room);
});

test('recording furniture=empty clears an orphan heavy_count', function () {
    $estimate = estimateWithRooms(1);
    $interview = app(FlooringInterview::class);
    $room = $estimate->rooms->first();

    foreach (['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'heavy'] as $k => $v) {
        $interview->recordAnswerFor($estimate, $k, $room->id, $v);
    }
    $interview->recordAnswerFor($estimate, 'heavy_count', $room->id, 3);

    // Downgrade furniture.
    $interview->recordAnswerFor($estimate, 'furniture', $room->id, 'empty');

    $answers = $estimate->interview_answers;
    expect($answers['rooms'][(string) $room->id])->toHaveKey('furniture');
    expect($answers['rooms'][(string) $room->id])->not->toHaveKey('heavy_count');
});

test('project-wide questions are asked in order after all room questions', function () {
    $estimate = estimateWithRooms(1);
    $interview = app(FlooringInterview::class);
    $room = $estimate->rooms->first();

    foreach (['material' => 'lvp', 'existing' => 'bare', 'subfloor' => 'none', 'furniture' => 'empty'] as $k => $v) {
        $interview->recordAnswerFor($estimate, $k, $room->id, $v);
    }

    $expected = ['customer_type', 'demo_haul_away', 'baseboards', 'quarter_round', 'transitions', 'door_undercuts', 'toilet_pulls'];

    foreach ($expected as $key) {
        $pending = $interview->nextQuestionFor($estimate);
        expect($pending)->not->toBeNull();
        expect($pending->question->key())->toBe($key);

        $interview->recordAnswerFor($estimate, $key, null, match ($key) {
            'customer_type' => 'person',
            'demo_haul_away' => 'van',
            'baseboards' => 'leave',
            'quarter_round' => 'new',
            'transitions', 'door_undercuts', 'toilet_pulls' => 2,
        });
    }

    expect($interview->nextQuestionFor($estimate))->toBeNull();
    expect($interview->isComplete($estimate))->toBeTrue();
});

test('empty rooms list goes straight to project-wide phase', function () {
    $estimate = estimateWithRooms(0);
    $interview = app(FlooringInterview::class);

    $pending = $interview->nextQuestionFor($estimate);

    expect($pending)->not->toBeNull();
    expect($pending->phase)->toBe(Phase::ProjectWide);
    expect($pending->question->key())->toBe('customer_type');
    expect($pending->totalRooms)->toBe(0);
});

test('legacy empty array shape normalizes without error', function () {
    $estimate = estimateWithRooms(1);
    // Simulate a row whose interview_answers is just an empty array.
    $estimate->forceFill(['interview_answers' => []])->save();
    $estimate->refresh();

    $interview = app(FlooringInterview::class);
    $pending = $interview->nextQuestionFor($estimate);

    expect($pending)->not->toBeNull();
    expect($pending->question->key())->toBe('material');
});

test('room phase question without room_id fails validation', function () {
    $estimate = estimateWithRooms(1);
    $interview = app(FlooringInterview::class);

    expect(fn () => $interview->recordAnswerFor($estimate, 'material', null, 'lvp'))
        ->toThrow(ValidationException::class);
});

test('project-wide phase question with room_id fails validation', function () {
    $estimate = estimateWithRooms(1);
    $interview = app(FlooringInterview::class);
    $room = $estimate->rooms->first();

    expect(fn () => $interview->recordAnswerFor($estimate, 'demo_haul_away', $room->id, 'van'))
        ->toThrow(ValidationException::class);
});

test('unknown question key fails validation', function () {
    $estimate = estimateWithRooms(1);
    $interview = app(FlooringInterview::class);
    $room = $estimate->rooms->first();

    expect(fn () => $interview->recordAnswerFor($estimate, 'bogus', $room->id, 'x'))
        ->toThrow(ValidationException::class);
});

test('invalid option throws validation exception', function () {
    $estimate = estimateWithRooms(1);
    $interview = app(FlooringInterview::class);
    $room = $estimate->rooms->first();

    expect(fn () => $interview->recordAnswerFor($estimate, 'material', $room->id, 'granite'))
        ->toThrow(ValidationException::class);
});

test('room question ordering constant contains all five room questions in fixed order', function () {
    expect(FlooringInterview::ROOM_QUESTIONS)->toBe([
        MaterialQuestion::class,
        ExistingQuestion::class,
        SubfloorQuestion::class,
        FurnitureQuestion::class,
        HeavyCountQuestion::class,
    ]);
});

test('project-wide question ordering constant contains all project-wide questions in fixed order', function () {
    expect(FlooringInterview::PROJECT_WIDE_QUESTIONS)->toBe([
        CustomerTypeQuestion::class,
        DemoHaulAwayQuestion::class,
        BaseboardsQuestion::class,
        QuarterRoundQuestion::class,
        TransitionsQuestion::class,
        DoorUndercutsQuestion::class,
        ToiletPullsQuestion::class,
    ]);
});
