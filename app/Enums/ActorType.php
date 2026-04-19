<?php

namespace App\Enums;

enum ActorType: string
{
    case Contractor = 'contractor';
    case Customer = 'customer';
    case System = 'system';
}
