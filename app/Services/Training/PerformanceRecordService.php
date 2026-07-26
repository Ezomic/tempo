<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Models\Activity;
use App\Models\User;

class PerformanceRecordService
{
    /**
     * Fold every activity's stored per-activity bests into the personal-record
     * and mean-max envelopes. Idempotent: it rebuilds both envelopes wholesale.
     */
    public function recompute(User $user): void
    {
        $activities = $user->activities()
            ->get(['id', 'sport', 'started_at', 'best_efforts', 'mean_max']);

        $records = [];
        $meanMax = [];

        foreach ($activities as $activity) {
            $this->foldRecords($activity, $records);
            $this->foldMeanMax($activity, $meanMax);
        }

        $user->personalRecords()->delete();
        foreach ($records as $row) {
            $user->personalRecords()->create($row);
        }

        $user->meanMaxEfforts()->delete();
        foreach ($meanMax as $row) {
            $user->meanMaxEfforts()->create($row);
        }
    }

    /**
     * @param  array<int, array{distance_m: int, duration_s: int, activity_id: int, achieved_on: string}>  $records
     */
    private function foldRecords(Activity $activity, array &$records): void
    {
        foreach ($activity->best_efforts ?? [] as $distance => $duration) {
            $distance = (int) $distance;
            $duration = (int) $duration;

            if (! isset($records[$distance]) || $duration < $records[$distance]['duration_s']) {
                $records[$distance] = [
                    'distance_m' => $distance,
                    'duration_s' => $duration,
                    'activity_id' => $activity->id,
                    'achieved_on' => $activity->started_at->toDateString(),
                ];
            }
        }
    }

    /**
     * @param  array<string, array{sport: string, duration_s: int, speed_mps: float, activity_id: int, achieved_on: string}>  $meanMax
     */
    private function foldMeanMax(Activity $activity, array &$meanMax): void
    {
        foreach ($activity->mean_max ?? [] as $duration => $speed) {
            $duration = (int) $duration;
            $speed = (float) $speed;
            $key = $activity->sport->value.'|'.$duration;

            if (! isset($meanMax[$key]) || $speed > $meanMax[$key]['speed_mps']) {
                $meanMax[$key] = [
                    'sport' => $activity->sport->value,
                    'duration_s' => $duration,
                    'speed_mps' => $speed,
                    'activity_id' => $activity->id,
                    'achieved_on' => $activity->started_at->toDateString(),
                ];
            }
        }
    }
}
