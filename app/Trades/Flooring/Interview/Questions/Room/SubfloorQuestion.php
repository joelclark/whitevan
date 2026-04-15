<?php

namespace App\Trades\Flooring\Interview\Questions\Room;

use App\Interviews\Question;
use App\Interviews\QuestionType;
use Illuminate\Validation\ValidationException;

class SubfloorQuestion extends Question
{
    public function key(): string
    {
        return 'subfloor';
    }

    public function label(): string
    {
        return 'What subfloor prep is needed?';
    }

    public function type(): QuestionType
    {
        return QuestionType::Select;
    }

    public function options(): array
    {
        return [
            'none' => 'None',
            'minor_patch' => 'Minor Patch',
            'major_self_level' => 'Major Self-Level',
        ];
    }

    public function validateAnswer(mixed $raw): string
    {
        if (! is_string($raw) || ! array_key_exists($raw, $this->options())) {
            throw ValidationException::withMessages([
                'value' => 'Choose one of the listed subfloor options.',
            ]);
        }

        return $raw;
    }
}
