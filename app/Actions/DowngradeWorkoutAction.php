<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\PlannedWorkout;
use RuntimeException;

class DowngradeWorkoutAction
{
    /**
     * Swap a hard planned session for its easier tier, recording the original
     * type and when it was adapted so adherence stays accurate.
     */
    public function handle(PlannedWorkout $workout): void
    {
        $type = $workout->workout_type;
        $downgrade = $type?->downgrade();

        if ($type === null || $downgrade === null) {
            throw new RuntimeException('This session cannot be downgraded.');
        }

        $workout->forceFill([
            'downgraded_from' => $type->value,
            'workout_type' => $downgrade,
            'adapted_at' => now(),
        ])->save();
    }
}
