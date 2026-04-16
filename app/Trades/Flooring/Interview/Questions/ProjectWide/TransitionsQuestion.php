<?php

namespace App\Trades\Flooring\Interview\Questions\ProjectWide;

use App\Interviews\Question;
use App\Interviews\QuestionType;
use Illuminate\Validation\ValidationException;

class TransitionsQuestion extends Question
{
    public function key(): string
    {
        return 'transitions';
    }

    public function label(): string
    {
        return 'How many transition strips job-wide?';
    }

    public function type(): QuestionType
    {
        return QuestionType::Count;
    }

    public function help(): ?string
    {
        return '5 means five or more.';
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
