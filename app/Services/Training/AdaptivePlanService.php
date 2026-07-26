<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Models\PlannedWorkout;

class AdaptivePlanService
{
    /**
     * A downgrade suggestion for today's session when readiness is low and the
     * session is hard. Null when there is nothing to suggest.
     *
     * @return array{planned_workout_id: int, from_label: string, to_type: string, to_label: string, reason: string}|null
     */
    public function suggestion(?PlannedWorkout $today, ?int $readinessScore): ?array
    {
        if ($today === null || $readinessScore === null || $today->workout_type === null) {
            return null;
        }

        // Already adapted, or readiness is fine: nothing to offer.
        if ($today->adapted_at !== null || $readinessScore > $this->threshold()) {
            return null;
        }

        $type = $today->workout_type;
        $downgrade = $type->downgrade();
        if (! $type->isHard() || $downgrade === null) {
            return null;
        }

        return [
            'planned_workout_id' => $today->id,
            'from_label' => $type->label(),
            'to_type' => $downgrade->value,
            'to_label' => $downgrade->label(),
            'reason' => "Readiness is {$readinessScore}. Swap {$type->label()} for an easier {$downgrade->label()} session?",
        ];
    }

    private function threshold(): int
    {
        return (int) config('training.readiness.downgrade_below');
    }
}
