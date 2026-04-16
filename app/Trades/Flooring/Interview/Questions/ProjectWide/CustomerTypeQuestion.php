<?php

namespace App\Trades\Flooring\Interview\Questions\ProjectWide;

use App\Interviews\Question;
use App\Interviews\QuestionType;
use Illuminate\Validation\ValidationException;

class CustomerTypeQuestion extends Question
{
    public function key(): string
    {
        return 'customer_type';
    }

    public function label(): string
    {
        return 'Is your customer a person or a business?';
    }

    public function type(): QuestionType
    {
        return QuestionType::Select;
    }

    public function options(): array
    {
        return [
            'person' => 'Person',
            'business' => 'Business',
        ];
    }

    public function validateAnswer(mixed $raw): string
    {
        if (! is_string($raw) || ! array_key_exists($raw, $this->options())) {
            throw ValidationException::withMessages([
                'value' => 'Choose whether the customer is a person or a business.',
            ]);
        }

        return $raw;
    }
}
