<?php

use App\Interviews\AnswerContext;
use App\Interviews\Phase;
use App\Trades\Flooring\Interview\Questions\LongTail\BaseboardsQuestion;
use App\Trades\Flooring\Interview\Questions\LongTail\DemoHaulAwayQuestion;
use App\Trades\Flooring\Interview\Questions\LongTail\DoorUndercutsQuestion;
use App\Trades\Flooring\Interview\Questions\LongTail\QuarterRoundQuestion;
use App\Trades\Flooring\Interview\Questions\LongTail\ToiletPullsQuestion;
use App\Trades\Flooring\Interview\Questions\LongTail\TransitionsQuestion;
use App\Trades\Flooring\Interview\Questions\Room\ExistingQuestion;
use App\Trades\Flooring\Interview\Questions\Room\FurnitureQuestion;
use App\Trades\Flooring\Interview\Questions\Room\HeavyCountQuestion;
use App\Trades\Flooring\Interview\Questions\Room\MaterialQuestion;
use App\Trades\Flooring\Interview\Questions\Room\SubfloorQuestion;
use Illuminate\Validation\ValidationException;

dataset('selectQuestions', [
    [MaterialQuestion::class],
    [ExistingQuestion::class],
    [SubfloorQuestion::class],
    [FurnitureQuestion::class],
    [DemoHaulAwayQuestion::class],
    [BaseboardsQuestion::class],
    [QuarterRoundQuestion::class],
]);

dataset('countQuestions', [
    [TransitionsQuestion::class],
    [DoorUndercutsQuestion::class],
    [ToiletPullsQuestion::class],
]);

test('select questions accept every declared option', function (string $class) {
    $question = new $class;

    foreach (array_keys($question->options()) as $option) {
        expect($question->validateAnswer($option))->toBe($option);
    }
})->with('selectQuestions');

test('select questions reject an unlisted option', function (string $class) {
    $question = new $class;

    expect(fn () => $question->validateAnswer('not-an-option'))
        ->toThrow(ValidationException::class);
})->with('selectQuestions');

test('select questions reject non-string values', function (string $class) {
    $question = new $class;

    expect(fn () => $question->validateAnswer(42))
        ->toThrow(ValidationException::class);
})->with('selectQuestions');

test('count questions accept values 0..5', function (string $class) {
    $question = new $class;

    foreach (range(0, 5) as $n) {
        expect($question->validateAnswer($n))->toBe($n);
    }
})->with('countQuestions');

test('count questions coerce numeric strings', function (string $class) {
    $question = new $class;

    expect($question->validateAnswer('3'))->toBe(3);
})->with('countQuestions');

test('count questions reject out-of-range values', function (string $class) {
    $question = new $class;

    expect(fn () => $question->validateAnswer(-1))
        ->toThrow(ValidationException::class);
    expect(fn () => $question->validateAnswer(6))
        ->toThrow(ValidationException::class);
})->with('countQuestions');

test('count questions reject non-integer values', function (string $class) {
    $question = new $class;

    expect(fn () => $question->validateAnswer('three'))
        ->toThrow(ValidationException::class);
    expect(fn () => $question->validateAnswer(1.5))
        ->toThrow(ValidationException::class);
})->with('countQuestions');

test('HeavyCountQuestion has min=1, max=5', function () {
    $question = new HeavyCountQuestion;
    expect($question->countMin())->toBe(1);
    expect($question->countMax())->toBe(5);
    expect(fn () => $question->validateAnswer(0))->toThrow(ValidationException::class);
    expect($question->validateAnswer(1))->toBe(1);
    expect($question->validateAnswer(5))->toBe(5);
});

test('HeavyCountQuestion::shouldAsk is true only when furniture is heavy', function () {
    $question = new HeavyCountQuestion;

    $ctx = fn (?string $furniture) => new AnswerContext(
        rooms: ['1' => $furniture !== null ? ['furniture' => $furniture] : []],
        longTail: [],
        currentRoomId: 1,
        phase: Phase::Room,
    );

    expect($question->shouldAsk($ctx('heavy')))->toBeTrue();
    expect($question->shouldAsk($ctx('empty')))->toBeFalse();
    expect($question->shouldAsk($ctx('move_replace')))->toBeFalse();
    expect($question->shouldAsk($ctx(null)))->toBeFalse();
});
