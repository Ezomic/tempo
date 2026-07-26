<?php

declare(strict_types=1);

namespace App\Services\Garmin;

use App\DataObjects\ActivitySummary;
use App\DataObjects\ConnectionStatus;
use App\DataObjects\LoginResult;
use App\DataObjects\WellnessSnapshot;
use App\Models\GarminConnection;
use Carbon\CarbonImmutable;

interface GarminClient
{
    public function login(GarminConnection $connection, string $email, string $password): LoginResult;

    public function resumeLoginWithMfa(GarminConnection $connection, string $loginToken, string $code): LoginResult;

    public function status(GarminConnection $connection): ConnectionStatus;

    /**
     * @return array<int, ActivitySummary>
     */
    public function activities(GarminConnection $connection, CarbonImmutable $start, CarbonImmutable $end): array;

    public function downloadFit(GarminConnection $connection, string $activityId): string;

    public function wellness(GarminConnection $connection, CarbonImmutable $date): WellnessSnapshot;

    /**
     * Upload a structured workout and schedule it on the given date. Returns the
     * Garmin workout id.
     *
     * @param  array{sport: string, name: string, estimated_seconds: int, steps: array<int, array<string, mixed>>}  $workout
     */
    public function pushWorkout(GarminConnection $connection, array $workout, CarbonImmutable $date): string;
}
