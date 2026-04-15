<?php

namespace App\Interviews;

enum QuestionType: string
{
    case Select = 'select';
    case Count = 'count';
}
