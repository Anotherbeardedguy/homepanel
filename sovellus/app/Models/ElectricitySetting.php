<?php

namespace App\Models;

use App\Enums\PriceBasis;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'price_basis',
    'green_below',
    'red_from',
    'message_green',
    'message_yellow',
    'message_red',
    'message_red_extra',
    'message_unknown',
])]
class ElectricitySetting extends Model
{
    protected function casts(): array
    {
        return [
            'price_basis' => PriceBasis::class,
            'green_below' => 'decimal:2',
            'red_from' => 'decimal:2',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'price_basis' => PriceBasis::IncVat,
            'green_below' => '8.00',
            'red_from' => '15.00',
            'message_green' => 'Nyt on hyvä aika pyykille.',
            'message_yellow' => 'Käytä sähköä tavalliseen tapaan.',
            'message_red' => 'Pyykki voi odottaa. Lähtisitkö ulos?',
            'message_red_extra' => 'Sammuta turhat valot.',
            'message_unknown' => 'Ajantasainen sähkön hinta ei ole saatavilla.',
        ]);
    }
}
