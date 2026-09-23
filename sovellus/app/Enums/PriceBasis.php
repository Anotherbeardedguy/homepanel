<?php

namespace App\Enums;

enum PriceBasis: string
{
    case ExVat = 'ex_vat';
    case IncVat = 'inc_vat';

    public function label(): string
    {
        return match ($this) {
            self::ExVat => 'Veroton spot',
            self::IncVat => 'Spot sisältää arvonlisäveron',
        };
    }
}
