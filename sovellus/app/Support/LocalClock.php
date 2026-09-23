<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class LocalClock
{
    public static function timezone(): string
    {
        return (string) config('homepanel.timezone');
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
    }

    public static function today(): string
    {
        return self::now()->toDateString();
    }
}
