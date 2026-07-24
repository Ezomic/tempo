<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\PlannedWorkout;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreatePlannedWorkoutAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data): PlannedWorkout
    {
        /** @var array<int, array<string, mixed>> $steps */
        $steps = $data['steps'] ?? [];
        unset($data['steps']);

        return DB::transaction(function () use ($user, $data, $steps): PlannedWorkout {
            $workout = $user->plannedWorkouts()->create($data);

            foreach (array_values($steps) as $position => $step) {
                $workout->steps()->create([
                    'position' => $position,
                    'repeat' => $step['repeat'],
                    'intensity' => $step['intensity'],
                    'duration_min' => $step['duration_min'],
                    'recovery_min' => $step['recovery_min'] ?? null,
                    'recovery_intensity' => $step['recovery_intensity'] ?? null,
                    'label' => $step['label'] ?? null,
                ]);
            }

            if ($steps !== []) {
                $workout->forceFill(['duration_min' => $workout->load('steps')->computedDurationMin()])->save();
            }

            return $workout->load('steps');
        });
    }
}
