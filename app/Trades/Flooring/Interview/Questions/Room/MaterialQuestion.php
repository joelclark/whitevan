<?php

namespace App\Trades\Flooring\Interview\Questions\Room;

use App\Interviews\Question;
use App\Interviews\QuestionType;
use Illuminate\Validation\ValidationException;

class MaterialQuestion extends Question
{
    public function key(): string
    {
        return 'material';
    }

    public function label(): string
    {
        return 'What material is going in?';
    }

    public function type(): QuestionType
    {
        return QuestionType::Select;
    }

    public function options(): array
    {
        return [
            'lvp' => 'LVP',
            'laminate' => 'Laminate',
            'engineered' => 'Engineered',
            'solid_hardwood' => 'Solid Hardwood',
            'tile' => 'Tile',
            'carpet' => 'Carpet',
            'sheet_vinyl' => 'Sheet Vinyl',
        ];
    }

    public function validateAnswer(mixed $raw): string
    {
        if (! is_string($raw) || ! array_key_exists($raw, $this->options())) {
            throw ValidationException::withMessages([
                'value' => 'Choose one of the listed materials.',
            ]);
        }

        return $raw;
    }
}
