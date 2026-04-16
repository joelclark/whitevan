<?php

namespace App\Trades\Flooring\Interview\Questions\ProjectWide;

use App\Interviews\Question;
use App\Interviews\QuestionType;
use Illuminate\Validation\ValidationException;

class QuarterRoundQuestion extends Question
{
    public function key(): string
    {
        return 'quarter_round';
    }

    public function label(): string
    {
        return 'What about quarter round?';
    }

    public function type(): QuestionType
    {
        return QuestionType::Select;
    }

    public function options(): array
    {
        return [
            'none' => 'None',
            'new' => 'New',
            'reuse' => 'Reuse',
        ];
    }

    public function validateAnswer(mixed $raw): string
    {
        if (! is_string($raw) || ! array_key_exists($raw, $this->options())) {
            throw ValidationException::withMessages([
                'value' => 'Choose one of the listed quarter-round options.',
            ]);
        }

        return $raw;
    }
}
