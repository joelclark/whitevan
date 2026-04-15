<?php

namespace App\Trades\Flooring\Interview\Questions\LongTail;

use App\Interviews\Question;
use App\Interviews\QuestionType;
use Illuminate\Validation\ValidationException;

class BaseboardsQuestion extends Question
{
    public function key(): string
    {
        return 'baseboards';
    }

    public function label(): string
    {
        return 'What happens with the baseboards?';
    }

    public function type(): QuestionType
    {
        return QuestionType::Select;
    }

    public function options(): array
    {
        return [
            'leave' => 'Leave',
            'remove_reinstall' => 'Remove & Reinstall',
            'remove_replace' => 'Remove & Replace',
        ];
    }

    public function validateAnswer(mixed $raw): string
    {
        if (! is_string($raw) || ! array_key_exists($raw, $this->options())) {
            throw ValidationException::withMessages([
                'value' => 'Choose one of the listed baseboard options.',
            ]);
        }

        return $raw;
    }
}
