<?php

namespace App\Trades\Flooring\Interview\Questions\ProjectWide;

use App\Interviews\Question;
use App\Interviews\QuestionType;
use Illuminate\Validation\ValidationException;

class DemoHaulAwayQuestion extends Question
{
    public function key(): string
    {
        return 'demo_haul_away';
    }

    public function label(): string
    {
        return 'How will demo debris be hauled away?';
    }

    public function type(): QuestionType
    {
        return QuestionType::Select;
    }

    public function options(): array
    {
        return [
            'none' => 'None',
            'van' => 'Van',
            'dumpster' => 'Dumpster',
        ];
    }

    public function validateAnswer(mixed $raw): string
    {
        if (! is_string($raw) || ! array_key_exists($raw, $this->options())) {
            throw ValidationException::withMessages([
                'value' => 'Choose one of the listed haul-away options.',
            ]);
        }

        return $raw;
    }
}
