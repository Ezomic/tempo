<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\SyncGarminAction;
use App\Models\GarminConnection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncGarminJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public GarminConnection $garminConnection) {}

    public function handle(SyncGarminAction $action): void
    {
        $action->handle($this->garminConnection);
    }
}
