<?php

namespace App\Interviews;

use Illuminate\Validation\ValidationException;

abstract class Question
{
    abstract public function key(): string;

    abstract public function label(): string;

    abstract public function type(): QuestionType;

    /**
     * @return array<string, string> Keyed by stored value; values are display labels.
     */
    public function options(): array
    {
        return [];
    }

    public function countMin(): int
    {
        return 0;
    }

    public function countMax(): int
    {
        return 5;
    }

    public function help(): ?string
    {
        return null;
    }

    public function shouldAsk(AnswerContext $ctx): bool
    {
        return true;
    }

    /**
     * Normalize and validate a raw request value. Throws on invalid input.
     *
     * @throws ValidationException
     */
    abstract public function validateAnswer(mixed $raw): string|int;
}
