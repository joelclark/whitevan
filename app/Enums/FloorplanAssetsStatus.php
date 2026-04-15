<?php

namespace App\Enums;

enum FloorplanAssetsStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';
}
