<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncGarminJob;
use App\Models\GarminConnection;
use Illuminate\Console\Command;

class SyncGarminCommand extends Command
{
    protected $signature = 'garmin:sync';

    protected $description = 'Queue a Garmin sync for every connected account';

    public function handle(): int
    {
        $connections = GarminConnection::query()
            ->where('status', GarminConnection::STATUS_CONNECTED)
            ->get();

        foreach ($connections as $connection) {
            SyncGarminJob::dispatch($connection);
        }

        $this->info("Queued {$connections->count()} Garmin sync job(s).");

        return self::SUCCESS;
    }
}
