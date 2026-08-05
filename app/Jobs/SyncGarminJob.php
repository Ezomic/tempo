<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\SyncGarminAction;
use App\Models\GarminConnection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class SyncGarminJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * A first sync walks 90 days of activities, downloading a .fit each, plus 30
     * wellness days that the sidecar deliberately throttles. The worker default
     * of 60s kills that mid-run, which is what used to strand the connection on
     * "syncing". Keep `queue.connections.*.retry_after` above this.
     */
    public int $timeout = 1800;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public GarminConnection $garminConnection) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        // A manual sync from settings and the four-hourly scheduled one must not
        // both walk the same account: they would download every .fit twice and
        // race each other's sync_status writes.
        return [
            (new WithoutOverlapping((string) $this->garminConnection->id))
                ->expireAfter($this->timeout)
                ->dontRelease(),
        ];
    }

    public function handle(SyncGarminAction $action): void
    {
        $action->handle($this->garminConnection);
    }

    /**
     * Reached when the job is killed by the worker timeout or runs out of tries,
     * neither of which unwinds through the action's own catch.
     */
    public function failed(?Throwable $e): void
    {
        $this->garminConnection->forceFill([
            'sync_status' => GarminConnection::SYNC_ERROR,
            'sync_status_since' => now(),
            'sync_error' => $e?->getMessage() ?? 'The sync stopped before it finished.',
        ])->save();
    }
}
