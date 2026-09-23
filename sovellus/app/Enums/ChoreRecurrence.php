<?php

namespace App\Enums;

enum ChoreRecurrence: string
{
    case Once = 'once';
    case Daily = 'daily';
    case Weekdays = 'weekdays';
    case Interval = 'interval';

    public function label(): string
    {
        return match ($this) {
            self::Once => 'Kerran',
            self::Daily => 'Päivittäin',
            self::Weekdays => 'Valittuina viikonpäivinä',
            self::Interval => 'Välein',
        };
    }
}
