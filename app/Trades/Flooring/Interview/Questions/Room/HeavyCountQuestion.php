<?php

namespace App\Trades\Flooring\Interview\Questions\Room;

use App\Interviews\AnswerContext;
use App\Interviews\Question;
use App\Interviews\QuestionType;
use Illuminate\Validation\ValidationException;

class HeavyCountQuestion extends Question
{
    public function key(): string
    {
        return 'heavy_count';
    }

    public function label(): string
    {
        return 'How many heavy items need to be moved?';
    }

    public function type(): QuestionType
    {
        return QuestionType::Count;
    }

    public function countMin(): int
    {
        return 1;
    }

    public function countMax(): int
    {
        return 5;
    }

    public function help(): ?string
    {
        return 'Pianos, safes, appliances, gun safes, etc. 5 means five or more.';
    }

    public function shouldAsk(AnswerContext $ctx): bool
    {
        return ($ctx->roomAnswers()['furniture'] ?? null) === 'heavy';
    }

    public function validateAnswer(mixed $raw): int
    {
        if (is_string($raw) && ctype_digit($raw)) {
            $raw = (int) $raw;
        }

        if (! is_int($raw) || $raw < $this->countMin() || $raw > $this->countMax()) {
            throw ValidationException::withMessages([
                'value' => "Enter a whole number between {$this->countMin()} and {$this->countMax()}.",
            ]);
        }

        return $raw;
    }
}
