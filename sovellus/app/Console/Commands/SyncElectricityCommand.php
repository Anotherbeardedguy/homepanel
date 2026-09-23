<?php

namespace App\Console\Commands;

use App\Services\Electricity\ElectricitySync;
use Illuminate\Console\Command;

class SyncElectricityCommand extends Command
{
    protected $signature = 'homepanel:sync-electricity';

    protected $description = 'Fetch Finnish quarter-hour spot prices';

    public function handle(ElectricitySync $sync): int
    {
        $sync->run();

        return self::SUCCESS;
    }
}
