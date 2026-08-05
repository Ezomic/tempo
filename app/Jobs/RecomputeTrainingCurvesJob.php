<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\SyncGarminAction;
use App\Models\GarminConnection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Tail of a backfill chain: the fitness curve and personal records only need
 * rebuilding once the whole range has landed, not per slice.
 */
class RecomputeTrainingCurvesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(public GarminConnection $garminConnection) {}

    public function handle(SyncGarminAction $action): void
    {
        $action->recomputeCurves($this->garminConnection);
    }
}
