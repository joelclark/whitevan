<?php

namespace App\Interviews;

enum Phase: string
{
    case Room = 'room';
    case LongTail = 'long_tail';
    case Done = 'done';
}
