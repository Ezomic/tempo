<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Models\PlannedWorkout;
use App\Models\PlannedWorkoutStep;

class WorkoutDescriber
{
    public function describe(PlannedWorkout $workout): string
    {
        $steps = $workout->steps;

        if ($steps->isEmpty()) {
            return $this->fallback($workout);
        }

        return $steps
            ->map(fn (PlannedWorkoutStep $step): string => $this->describeStep($step))
            ->implode(' ');
    }

    private function describeStep(PlannedWorkoutStep $step): string
    {
        $intensity = $step->intensity;
        $work = "{$step->duration_min} min {$intensity->label()} (Z{$intensity->zone()}, ~{$intensity->hrPercent()} max HR)";

        if ($step->repeat > 1) {
            $work = "{$step->repeat} x {$work}";
        }

        if ($step->recovery_min !== null && $step->recovery_min > 0) {
            $recovery = $step->recovery_intensity ?? $intensity;
            $work .= " with {$step->recovery_min} min {$recovery->label()} between";
        }

        $prefix = $step->label !== null && $step->label !== '' ? "{$step->label}: " : '';

        return "{$prefix}{$work}.";
    }

    private function fallback(PlannedWorkout $workout): string
    {
        if ($workout->notes !== null && $workout->notes !== '') {
            return $workout->notes;
        }

        return $workout->duration_min !== null
            ? "{$workout->duration_min} min."
            : $workout->title;
    }
}
