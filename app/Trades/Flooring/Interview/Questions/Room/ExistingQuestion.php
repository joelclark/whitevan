<?php

namespace App\Trades\Flooring\Interview\Questions\Room;

use App\Interviews\Question;
use App\Interviews\QuestionType;
use Illuminate\Validation\ValidationException;

class ExistingQuestion extends Question
{
    public function key(): string
    {
        return 'existing';
    }

    public function label(): string
    {
        return 'What is on the floor today?';
    }

    public function type(): QuestionType
    {
        return QuestionType::Select;
    }

    public function options(): array
    {
        return [
            'bare' => 'Bare',
            'carpet' => 'Carpet',
            'lvp' => 'LVP',
            'laminate' => 'Laminate',
            'hardwood' => 'Hardwood',
            'tile' => 'Tile',
            'sheet_vinyl' => 'Sheet Vinyl',
        ];
    }

    public function validateAnswer(mixed $raw): string
    {
        if (! is_string($raw) || ! array_key_exists($raw, $this->options())) {
            throw ValidationException::withMessages([
                'value' => 'Choose one of the listed existing materials.',
            ]);
        }

        return $raw;
    }
}
