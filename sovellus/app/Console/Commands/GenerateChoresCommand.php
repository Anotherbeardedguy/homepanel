<?php

namespace App\Console\Commands;

use App\Services\ChoreOccurrenceService;
use Illuminate\Console\Command;

class GenerateChoresCommand extends Command
{
    protected $signature = 'homepanel:generate-chores';

    protected $description = 'Create missing chore occurrences for the local household calendar';

    public function handle(ChoreOccurrenceService $occurrences): int
    {
        $occurrences->syncAll();
        $this->info('Chore occurrences are up to date.');

        return self::SUCCESS;
    }
}
