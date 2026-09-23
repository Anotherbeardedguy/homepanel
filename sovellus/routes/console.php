<?php

use Illuminate\Support\Facades\Schedule;

$timezone = config('homepanel.timezone');

Schedule::command('homepanel:generate-chores')
    ->dailyAt('00:05')
    ->timezone($timezone);

Schedule::command('homepanel:sync-electricity')
    ->hourly()
    ->timezone($timezone)
    ->withoutOverlapping(10);

Schedule::command('homepanel:sync-weather')
    ->everyThirtyMinutes()
    ->timezone($timezone)
    ->withoutOverlapping(10);

Schedule::command('homepanel:sync-calendar')
    ->everyFiveMinutes()
    ->timezone($timezone)
    ->withoutOverlapping(10);
