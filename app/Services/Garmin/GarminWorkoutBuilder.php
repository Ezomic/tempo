<?php

declare(strict_types=1);

namespace App\Services\Garmin;

use App\Enums\Sport;
use App\Models\PlannedWorkout;
use App\Models\PlannedWorkoutStep;

class GarminWorkoutBuilder
{
    /**
     * Translate a planned workout into the sidecar's structured-workout body.
     *
     * @return array{sport: string, name: string, estimated_seconds: int, steps: array<int, array<string, mixed>>}
     */
    public function build(PlannedWorkout $workout): array
    {
        $steps = $workout->steps->values();
        $last = $steps->count() - 1;

        $entries = [];
        foreach ($steps as $i => $step) {
            array_push($entries, ...$this->stepEntries($step, $i === 0, $i === $last));
        }

        $minutes = $workout->computedDurationMin();
        if ($minutes === 0) {
            $minutes = $workout->duration_min ?? 0;
        }

        return [
            'sport' => $workout->sport === Sport::Bike ? 'cycling' : 'running',
            'name' => $workout->title,
            'estimated_seconds' => $minutes * 60,
            'steps' => $entries,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stepEntries(PlannedWorkoutStep $step, bool $isFirst, bool $isLast): array
    {
        $hasRecovery = ($step->recovery_min ?? 0) > 0;

        $work = [
            'type' => 'interval',
            'seconds' => $step->duration_min * 60,
            'description' => $step->label ?? $step->intensity->label(),
        ];

        if ($step->repeat > 1) {
            $inner = [$work];
            if ($hasRecovery) {
                $inner[] = $this->recoveryEntry($step);
            }

            return [[
                'type' => 'repeat',
                'iterations' => $step->repeat,
                'steps' => $inner,
            ]];
        }

        // A single-rep first/last step reads better on the watch as a warm-up or
        // cool-down screen than a plain interval.
        if ($isFirst) {
            $work['type'] = 'warmup';
        } elseif ($isLast && ! $hasRecovery) {
            $work['type'] = 'cooldown';
        }

        $entries = [$work];
        if ($hasRecovery) {
            $entries[] = $this->recoveryEntry($step);
        }

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    private function recoveryEntry(PlannedWorkoutStep $step): array
    {
        return [
            'type' => 'recovery',
            'seconds' => (int) $step->recovery_min * 60,
            'description' => $step->recovery_intensity?->label() ?? 'Recovery',
        ];
    }
}
