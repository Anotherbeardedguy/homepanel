<?php

namespace App\Services\Weather;

final class WeatherSymbols
{
    public static function describe(?int $symbol): ?string
    {
        return match ($symbol) {
            1 => 'Selkeää',
            2 => 'Puolipilvistä',
            3, 4 => 'Pilvistä',
            21 => 'Heikkoa vesisadetta',
            22 => 'Vesisadetta',
            23 => 'Voimakasta vesisadetta',
            31 => 'Heikkoa räntäsadetta',
            32 => 'Räntäsadetta',
            33 => 'Voimakasta räntäsadetta',
            41 => 'Heikkoa lumisadetta',
            42 => 'Lumisadetta',
            43 => 'Voimakasta lumisadetta',
            51 => 'Heikkoa tihkusadetta',
            52 => 'Tihkusadetta',
            53 => 'Voimakasta tihkusadetta',
            61 => 'Ukkoskuuroja',
            62 => 'Voimakkaita ukkoskuuroja',
            63 => 'Ukkosta',
            71 => 'Heikkoja lumikuuroja',
            72 => 'Lumikuuroja',
            73 => 'Voimakkaita lumikuuroja',
            81 => 'Heikkoja räntäkuuroja',
            82 => 'Räntäkuuroja',
            83 => 'Voimakkaita räntäkuuroja',
            91 => 'Heikkoja sadekuuroja',
            92 => 'Sadekuuroja',
            93 => 'Voimakkaita sadekuuroja',
            default => null,
        };
    }
}
