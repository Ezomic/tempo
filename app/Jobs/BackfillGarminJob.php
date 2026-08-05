<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\SyncGarminAction;
use App\Models\GarminConnection;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class BackfillGarminJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** One slice pulls a .fit per activity, so it needs the same room as a sync. */
    public int $timeout = 1800;

    /** @var array<int, int> */
    public array $backoff = [120, 600];

    public function __construct(
        public GarminConnection $garminConnection,
        public string $start,
        public string $end,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        // Shares SyncGarminJob's lock key: a backfill and a scheduled sync both
        // download archives, and Garmin rate-limits. Released rather than
        // dropped, since a skipped slice would leave a hole in the history.
        return [
            (new WithoutOverlapping((string) $this->garminConnection->id))
                ->expireAfter($this->timeout)
                ->releaseAfter(120),
        ];
    }

    public function handle(SyncGarminAction $action): void
    {
        $action->backfill(
            $this->garminConnection,
            CarbonImmutable::parse($this->start),
            CarbonImmutable::parse($this->end),
        );
    }
}
