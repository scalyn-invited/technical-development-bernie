<?php

namespace App\Enums;

enum WeeklyEntryStatus: string
{
    case Planned = 'planned';
    case Evidenced = 'evidenced';
    case Closed = 'closed';
}
