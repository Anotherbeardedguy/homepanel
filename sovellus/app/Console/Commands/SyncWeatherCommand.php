<?php

namespace App\Console\Commands;

use App\Services\Weather\WeatherSync;
use Illuminate\Console\Command;

class SyncWeatherCommand extends Command
{
    protected $signature = 'homepanel:sync-weather';

    protected $description = 'Fetch the Ilmatieteen laitos observation and forecast';

    public function handle(WeatherSync $sync): int
    {
        $sync->run();

        return self::SUCCESS;
    }
}
