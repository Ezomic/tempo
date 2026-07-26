<?php

declare(strict_types=1);

use App\Enums\Sport;
use App\Enums\WorkoutType;
use App\Models\Activity;
use App\Models\PlannedWorkout;
use App\Models\User;
use App\Services\Training\FitnessCurveService;
use Carbon\CarbonImmutable;

function seedActivity(User $user, string $date, float $trimp): void
{
    Activity::create([
        'user_id' => $user->id,
        'external_id' => uniqid('act_', true),
        'sport' => Sport::Run,
        'started_at' => CarbonImmutable::parse($date),
        'trimp' => $trimp,
    ]);
}

it('stores one metric row per day from first activity to today', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-07-15');

    seedActivity($user, '2026-07-13', 100);

    (new FitnessCurveService)->recompute($user, $today);

    expect($user->dailyLoadMetrics()->count())->toBe(3); // 13th, 14th, 15th
});

it('decays fitness and fatigue and lags form by a day', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-07-14');

    seedActivity($user, '2026-07-13', 100);

    (new FitnessCurveService)->recompute($user, $today);

    $first = $user->dailyLoadMetrics()->orderBy('date')->first();
    $second = $user->dailyLoadMetrics()->orderBy('date')->skip(1)->first();

    // Day one: form is yesterday's CTL - ATL, both zero before any load.
    expect($first->tsb)->toBe(0.0)
        ->and($first->ctl)->toBe(2.4)
        ->and($first->atl)->toBe(13.3)
        // Day two: no load, fatigue falls faster than fitness so form dips.
        ->and($second->tsb)->toBe(-11.0)
        ->and($second->ctl)->toBeLessThan($first->ctl);
});

it('clears the series when the user has no activities', function () {
    $user = User::factory()->create();
    seedActivity($user, '2026-07-13', 100);
    (new FitnessCurveService)->recompute($user, CarbonImmutable::parse('2026-07-13'));
    expect($user->dailyLoadMetrics()->count())->toBe(1);

    $user->activities()->delete();
    (new FitnessCurveService)->recompute($user, CarbonImmutable::parse('2026-07-13'));

    expect($user->dailyLoadMetrics()->count())->toBe(0);
});

it('projects future load from planned sessions', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-07-15');

    seedActivity($user, '2026-07-15', 60);
    PlannedWorkout::create([
        'user_id' => $user->id,
        'date' => '2026-07-16',
        'sport' => Sport::Run,
        'workout_type' => WorkoutType::Intervals,
        'title' => 'Intervals',
        'duration_min' => 60,
    ]);

    $service = new FitnessCurveService;
    $service->recompute($user, $today);
    $projection = $service->project($user, $today, 14);

    expect($projection)->toHaveCount(14)
        ->and($projection[0]['date'])->toBe('2026-07-16')
        ->and($projection[13]['date'])->toBe('2026-07-29')
        // A hard planned session pushes projected fatigue up on that day.
        ->and($projection[0]['atl'])->toBeGreaterThan(
            $user->dailyLoadMetrics()->orderByDesc('date')->first()->atl,
        );
});
