<?php

namespace App\Enums;

enum PlanStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
}
