<?php

namespace App\Enums;

enum ShiftStatus: string
{
    case Booked = 'booked';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
