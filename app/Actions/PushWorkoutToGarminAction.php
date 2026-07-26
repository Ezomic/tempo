<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\PlannedWorkout;
use App\Services\Garmin\GarminClient;
use App\Services\Garmin\GarminWorkoutBuilder;
use RuntimeException;

class PushWorkoutToGarminAction
{
    public function __construct(
        private readonly GarminClient $client,
        private readonly GarminWorkoutBuilder $builder,
    ) {}

    public function handle(PlannedWorkout $workout): void
    {
        $workout->loadMissing(['steps', 'user.garminConnection']);

        $connection = $workout->user->garminConnection;

        if ($connection === null || ! $connection->isConnected()) {
            throw new RuntimeException('Garmin is not connected.');
        }

        $workoutId = $this->client->pushWorkout(
            $connection,
            $this->builder->build($workout),
            $workout->date->toImmutable(),
        );

        $workout->update([
            'garmin_workout_id' => $workoutId,
            'garmin_pushed_at' => now(),
        ]);
    }
}
