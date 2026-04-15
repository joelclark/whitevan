<?php

namespace App\Trades\Flooring\Interview\Questions\Room;

use App\Interviews\Question;
use App\Interviews\QuestionType;
use Illuminate\Validation\ValidationException;

class FurnitureQuestion extends Question
{
    public function key(): string
    {
        return 'furniture';
    }

    public function label(): string
    {
        return 'How is the room furnished?';
    }

    public function type(): QuestionType
    {
        return QuestionType::Select;
    }

    public function options(): array
    {
        return [
            'empty' => 'Empty',
            'move_replace' => 'Move & Replace',
            'heavy' => 'Heavy Items',
        ];
    }

    public function validateAnswer(mixed $raw): string
    {
        if (! is_string($raw) || ! array_key_exists($raw, $this->options())) {
            throw ValidationException::withMessages([
                'value' => 'Choose one of the listed furniture options.',
            ]);
        }

        return $raw;
    }
}
