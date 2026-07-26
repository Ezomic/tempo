<?php

declare(strict_types=1);

namespace App\Services\Garmin;

use App\Enums\Intensity;
use App\Enums\Sport;
use App\Models\PlannedWorkout;
use App\Models\PlannedWorkoutStep;

class GarminWorkoutBuilder
{
    /**
     * Fixed beginner running pace per intensity, in seconds per km (the target
     * centre). Deliberately gentle and history-independent, so a new runner gets
     * a sensible target from day one. A +/- window is applied to form the range.
     */
    private const RUN_PACE_SEC_PER_KM = [
        'recovery' => 480,
        'easy' => 440,
        'steady' => 390,
        'hard' => 345,
        'max' => 310,
    ];

    private const PACE_WINDOW_SEC = 20;

    /**
     * Translate a planned workout into the sidecar's structured-workout body.
     *
     * @return array{sport: string, name: string, estimated_seconds: int, steps: array<int, array<string, mixed>>}
     */
    public function build(PlannedWorkout $workout): array
    {
        $entries = [];
        foreach ($workout->steps as $step) {
            array_push($entries, ...$this->stepEntries($step, $workout->sport));
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
    private function stepEntries(PlannedWorkoutStep $step, Sport $sport): array
    {
        $hasRecovery = ($step->recovery_min ?? 0) > 0;

        // Every work step is an interval. Position-based warm-up/cool-down
        // guessing mislabels the main effort when it happens to be first or last,
        // so the step's own label carries any "Warm up" / "Cool down" meaning.
        $work = [
            'type' => 'interval',
            'seconds' => $step->duration_min * 60,
            'description' => $step->label ?? ($sport === Sport::Bike ? 'Ride' : 'Jog'),
        ];

        // Pace targets only make sense running; MTB pace varies too much by terrain.
        if ($sport === Sport::Run) {
            $work += $this->runPaceTarget($step->intensity);
        }

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

    /**
     * A pace-zone target as a min/max speed in m/s, from the fixed beginner table.
     *
     * @return array{target_pace_low: float, target_pace_high: float}
     */
    private function runPaceTarget(Intensity $intensity): array
    {
        $centre = self::RUN_PACE_SEC_PER_KM[$intensity->value];
        $slow = $centre + self::PACE_WINDOW_SEC;
        $fast = $centre - self::PACE_WINDOW_SEC;

        return [
            'target_pace_low' => round(1000 / $slow, 3),
            'target_pace_high' => round(1000 / $fast, 3),
        ];
    }
}
