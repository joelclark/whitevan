<?php

namespace App\Trades\Flooring\Interview;

use App\Interviews\AnswerContext;
use App\Interviews\PendingQuestion;
use App\Interviews\Phase;
use App\Interviews\Question;
use App\Interviews\TradeInterview;
use App\Models\Estimate;
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

class FlooringInterview implements TradeInterview
{
    /** @var list<class-string<Question>> */
    public const ROOM_QUESTIONS = [
        MaterialQuestion::class,
        ExistingQuestion::class,
        SubfloorQuestion::class,
        FurnitureQuestion::class,
        HeavyCountQuestion::class,
    ];

    /** @var list<class-string<Question>> */
    public const PROJECT_WIDE_QUESTIONS = [
        CustomerTypeQuestion::class,
        DemoHaulAwayQuestion::class,
        BaseboardsQuestion::class,
        QuarterRoundQuestion::class,
        TransitionsQuestion::class,
        DoorUndercutsQuestion::class,
        ToiletPullsQuestion::class,
    ];

    public function nextQuestionFor(Estimate $estimate): ?PendingQuestion
    {
        $answers = $this->normalize($estimate);
        $rooms = $estimate->rooms()->orderBy('position')->get();
        $total = $rooms->count();

        foreach ($rooms as $index => $room) {
            foreach (self::ROOM_QUESTIONS as $class) {
                $question = app($class);
                $ctx = new AnswerContext(
                    $answers['rooms'],
                    $answers['project_wide'],
                    $room->id,
                    Phase::Room,
                );

                if (! $question->shouldAsk($ctx)) {
                    continue;
                }

                if (array_key_exists($question->key(), $ctx->roomAnswers())) {
                    continue;
                }

                return new PendingQuestion($question, Phase::Room, $room, $index, $total);
            }
        }

        foreach (self::PROJECT_WIDE_QUESTIONS as $class) {
            $question = app($class);
            $ctx = new AnswerContext(
                $answers['rooms'],
                $answers['project_wide'],
                null,
                Phase::ProjectWide,
            );

            if (! $question->shouldAsk($ctx)) {
                continue;
            }

            if (array_key_exists($question->key(), $answers['project_wide'])) {
                continue;
            }

            return new PendingQuestion($question, Phase::ProjectWide, null, -1, $total);
        }

        return null;
    }

    public function recordAnswerFor(
        Estimate $estimate,
        string $questionKey,
        ?int $roomId,
        mixed $rawValue,
    ): void {
        [$question, $isRoomPhase] = $this->resolveQuestion($questionKey);

        if ($isRoomPhase && $roomId === null) {
            throw ValidationException::withMessages([
                'room_id' => 'A room is required for this question.',
            ]);
        }

        if (! $isRoomPhase && $roomId !== null) {
            throw ValidationException::withMessages([
                'room_id' => 'This question is not scoped to a room.',
            ]);
        }

        if ($isRoomPhase && ! $estimate->rooms()->whereKey($roomId)->exists()) {
            throw ValidationException::withMessages([
                'room_id' => 'Unknown room.',
            ]);
        }

        $value = $question->validateAnswer($rawValue);
        $answers = $this->normalize($estimate);

        if ($isRoomPhase) {
            $roomKey = (string) $roomId;
            $roomAnswers = $answers['rooms'][$roomKey] ?? [];
            $roomAnswers[$questionKey] = $value;

            // Orphan clearing: centralize every conditional invariant here so
            // the rules are auditable in one place as new dependencies land.
            if ($questionKey === 'furniture' && $value !== 'heavy') {
                unset($roomAnswers['heavy_count']);
            }

            $answers['rooms'][$roomKey] = $roomAnswers;
        } else {
            $answers['project_wide'][$questionKey] = $value;
        }

        $estimate->interview_answers = $answers;
    }

    public function isComplete(Estimate $estimate): bool
    {
        return $this->nextQuestionFor($estimate) === null;
    }

    public function catalog(): array
    {
        return [
            'room' => array_map(fn (string $class) => $this->questionMetadata(app($class)), self::ROOM_QUESTIONS),
            'project_wide' => array_map(fn (string $class) => $this->questionMetadata(app($class)), self::PROJECT_WIDE_QUESTIONS),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function questionMetadata(Question $question): array
    {
        return [
            'key' => $question->key(),
            'label' => $question->label(),
            'help' => $question->help(),
            'type' => $question->type()->value,
            'options' => $question->options(),
            'count_min' => $question->countMin(),
            'count_max' => $question->countMax(),
        ];
    }

    /**
     * @return array{rooms: array<string, array<string, string|int>>, project_wide: array<string, string|int>}
     */
    private function normalize(Estimate $estimate): array
    {
        $raw = $estimate->getAttribute('interview_answers');
        $arr = $this->toPlainArray($raw);

        $rooms = $this->toPlainArray($arr['rooms'] ?? []);
        $projectWide = $this->toPlainArray($arr['project_wide'] ?? []);

        $cleanRooms = [];
        foreach ($rooms as $roomId => $roomAnswers) {
            $cleanRooms[(string) $roomId] = $this->toPlainArray($roomAnswers);
        }

        return ['rooms' => $cleanRooms, 'project_wide' => $projectWide];
    }

    /**
     * @return array<string, mixed>
     */
    private function toPlainArray(mixed $value): array
    {
        if ($value instanceof \ArrayObject) {
            return $value->getArrayCopy();
        }

        if (is_array($value)) {
            return $value;
        }

        return [];
    }

    /**
     * @return array{0: Question, 1: bool} [question, isRoomPhase]
     */
    private function resolveQuestion(string $questionKey): array
    {
        foreach (self::ROOM_QUESTIONS as $class) {
            $question = app($class);
            if ($question->key() === $questionKey) {
                return [$question, true];
            }
        }

        foreach (self::PROJECT_WIDE_QUESTIONS as $class) {
            $question = app($class);
            if ($question->key() === $questionKey) {
                return [$question, false];
            }
        }

        throw ValidationException::withMessages([
            'question_key' => 'Unknown question.',
        ]);
    }
}
