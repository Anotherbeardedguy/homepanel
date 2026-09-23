<?php

namespace App\Enums;

enum AbsencePattern: string
{
    case Range = 'range';
    case EveryOtherWeek = 'every_other_week';
    case EveryOtherWeekend = 'every_other_weekend';

    public function label(): string
    {
        return match ($this) {
            self::Range => 'Aikaväli',
            self::EveryOtherWeek => 'Joka toinen viikko',
            self::EveryOtherWeekend => 'Joka toinen viikonloppu',
        };
    }
}
