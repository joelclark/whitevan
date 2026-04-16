<?php

namespace App\Interviews;

enum Phase: string
{
    case Room = 'room';
    case ProjectWide = 'project_wide';
    case Done = 'done';
}
