<?php

namespace App\Console\Commands;

use App\Services\Calendar\CalendarSync;
use Illuminate\Console\Command;

class SyncCalendarCommand extends Command
{
    protected $signature = 'homepanel:sync-calendar';

    protected $description = 'Sync the selected Google calendars';

    public function handle(CalendarSync $sync): int
    {
        $sync->run();

        return self::SUCCESS;
    }
}
