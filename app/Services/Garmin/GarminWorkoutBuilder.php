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
            array_push($entries, ...$this->stepEntries($step, $workout->sport, $i === 0, $i === $last));
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
    private function stepEntries(PlannedWorkoutStep $step, Sport $sport, bool $isFirst, bool $isLast): array
    {
        $hasRecovery = ($step->recovery_min ?? 0) > 0;

        // A single-rep first/last step reads better on the watch as a warm-up or
        // cool-down screen than a plain interval.
        $type = 'interval';
        if ($step->repeat <= 1) {
            if ($isFirst) {
                $type = 'warmup';
            } elseif ($isLast && ! $hasRecovery) {
                $type = 'cooldown';
            }
        }

        $work = [
            'type' => $type,
            'seconds' => $step->duration_min * 60,
            'description' => $step->label ?? $this->workWord($sport, $type),
        ];

        if ($step->repeat > 1) {
            $inner = [$work];
            if ($hasRecovery) {
                $inner[] = $this->recoveryEntry($step, $sport);
            }

            return [[
                'type' => 'repeat',
                'iterations' => $step->repeat,
                'steps' => $inner,
            ]];
        }

        $entries = [$work];
        if ($hasRecovery) {
            $entries[] = $this->recoveryEntry($step, $sport);
        }

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    private function recoveryEntry(PlannedWorkoutStep $step, Sport $sport): array
    {
        return [
            'type' => 'recovery',
            'seconds' => (int) $step->recovery_min * 60,
            'description' => $sport === Sport::Bike ? 'Easy spin' : 'Walk',
        ];
    }

    private function workWord(Sport $sport, string $type): string
    {
        return match ($type) {
            'warmup' => 'Warm up',
            'cooldown' => 'Cool down',
            default => $sport === Sport::Bike ? 'Ride' : 'Jog',
        };
    }
}
