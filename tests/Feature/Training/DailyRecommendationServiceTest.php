<?php

declare(strict_types=1);

use App\Enums\HrvStatus;
use App\Enums\Sport;
use App\Enums\WorkoutType;
use App\Models\Activity;
use App\Models\PlannedWorkout;
use App\Models\User;
use App\Models\WellnessDay;
use App\Services\Training\DailyRecommendationService;
use Carbon\CarbonImmutable;

function wellnessFor(User $user, CarbonImmutable $date, HrvStatus $hrv): void
{
    WellnessDay::create([
        'user_id' => $user->id,
        'date' => $date->toDateString(),
        'hrv_status' => $hrv,
        'body_battery_high' => 80,
        'sleep_score' => 70,
    ]);
}

function planFor(User $user, CarbonImmutable $date, WorkoutType $type): PlannedWorkout
{
    return PlannedWorkout::create([
        'user_id' => $user->id,
        'date' => $date->toDateString(),
        'sport' => Sport::Run,
        'workout_type' => $type,
        'title' => ucfirst($type->value),
    ]);
}

it('recommends easing a hard session on low readiness and links the downgrade', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::now();
    wellnessFor($user, $today, HrvStatus::Poor); // -45 -> score 55
    $plan = planFor($user, $today, WorkoutType::Intervals);

    $result = app(DailyRecommendationService::class)->forToday($user, $today);

    expect($result['action'])->toBe('ease')
        ->and($result['planned_workout_id'])->toBe($plan->id);
});

it('recommends rest when nothing is planned and readiness is low', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::now();
    wellnessFor($user, $today, HrvStatus::Poor);

    expect(app(DailyRecommendationService::class)->forToday($user, $today)['action'])
        ->toBe('rest');
});

it('backs off regardless of plan when load is in the danger band', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::now();
    wellnessFor($user, $today, HrvStatus::Balanced);
    // Heavy load only in the last week -> ACWR spikes into danger.
    for ($i = 0; $i <= 6; $i++) {
        Activity::create([
            'user_id' => $user->id,
            'external_id' => uniqid('load_', true),
            'sport' => Sport::Run,
            'started_at' => $today->subDays($i),
            'trimp' => 130,
        ]);
    }
    planFor($user, $today, WorkoutType::Easy);

    $result = app(DailyRecommendationService::class)->forToday($user, $today);

    expect($result['reason'])->toContain('spiking');
});

it('proceeds when everything looks fine', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::now();
    wellnessFor($user, $today, HrvStatus::Balanced);
    planFor($user, $today, WorkoutType::Easy);

    expect(app(DailyRecommendationService::class)->forToday($user, $today)['action'])
        ->toBe('proceed');
});
