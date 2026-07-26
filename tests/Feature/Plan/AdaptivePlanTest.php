<?php

declare(strict_types=1);

use App\Enums\Sport;
use App\Enums\WorkoutType;
use App\Models\PlannedWorkout;
use App\Models\User;
use App\Services\Training\AdaptivePlanService;
use Carbon\CarbonImmutable;

function plannedToday(User $user, WorkoutType $type): PlannedWorkout
{
    return PlannedWorkout::create([
        'user_id' => $user->id,
        'date' => CarbonImmutable::now()->toDateString(),
        'sport' => Sport::Run,
        'workout_type' => $type,
        'title' => $type->label(),
        'duration_min' => 60,
    ]);
}

it('suggests a downgrade for a hard session when readiness is low', function () {
    $user = User::factory()->create();
    $workout = plannedToday($user, WorkoutType::Intervals);

    $suggestion = (new AdaptivePlanService)->suggestion($workout, 50);

    expect($suggestion)->not->toBeNull()
        ->and($suggestion['to_type'])->toBe('easy')
        ->and($suggestion['planned_workout_id'])->toBe($workout->id);
});

it('does not suggest a downgrade when readiness is fine', function () {
    $user = User::factory()->create();
    $workout = plannedToday($user, WorkoutType::Intervals);

    expect((new AdaptivePlanService)->suggestion($workout, 80))->toBeNull();
});

it('does not suggest a downgrade for an easy session', function () {
    $user = User::factory()->create();
    $workout = plannedToday($user, WorkoutType::Easy);

    expect((new AdaptivePlanService)->suggestion($workout, 40))->toBeNull();
});

it('does not re-suggest once a session is adapted', function () {
    $user = User::factory()->create();
    $workout = plannedToday($user, WorkoutType::Intervals);
    $workout->forceFill(['adapted_at' => now()])->save();

    expect((new AdaptivePlanService)->suggestion($workout, 40))->toBeNull();
});

it('downgrades a session through the endpoint and records it', function () {
    $user = User::factory()->create();
    $workout = plannedToday($user, WorkoutType::Tempo);

    $this->actingAs($user)
        ->post("/plan/{$workout->id}/downgrade")
        ->assertRedirect();

    $workout->refresh();
    expect($workout->workout_type)->toBe(WorkoutType::Easy)
        ->and($workout->downgraded_from)->toBe('tempo')
        ->and($workout->adapted_at)->not->toBeNull();
});

it('forbids downgrading another user\'s session', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $workout = plannedToday($owner, WorkoutType::Tempo);

    $this->actingAs($other)
        ->post("/plan/{$workout->id}/downgrade")
        ->assertForbidden();
});
