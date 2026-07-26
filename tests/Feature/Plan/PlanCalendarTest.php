<?php

declare(strict_types=1);

use App\Actions\PushPlannedWorkoutAction;
use App\Enums\Sport;
use App\Enums\WorkoutType;
use App\Models\PlannedWorkout;
use App\Models\User;
use Carbon\CarbonImmutable;

function calendarWorkout(User $user, string $date, array $extra = []): PlannedWorkout
{
    return PlannedWorkout::create(array_merge([
        'user_id' => $user->id,
        'date' => $date,
        'sport' => Sport::Run,
        'workout_type' => WorkoutType::Easy,
        'title' => 'Session',
    ], $extra));
}

it('renders the plan calendar', function () {
    $this->actingAs(User::factory()->create())->get('/plan/calendar')->assertOk();
});

it('moves a session to another day and it persists', function () {
    $user = User::factory()->create();
    $workout = calendarWorkout($user, CarbonImmutable::now()->startOfWeek()->toDateString());
    $target = CarbonImmutable::now()->startOfWeek()->addDays(3)->toDateString();

    $this->actingAs($user)
        ->post("/plan/{$workout->id}/move", ['date' => $target])
        ->assertRedirect();

    expect($workout->fresh()->date->toDateString())->toBe($target);
});

it('re-syncs the calendar when a pushed session moves', function () {
    $user = User::factory()->create();
    $workout = calendarWorkout($user, CarbonImmutable::now()->startOfWeek()->toDateString());
    $workout->forceFill(['pushed_at' => now(), 'chronos_event_id' => 'evt_123'])->save();

    $this->mock(PushPlannedWorkoutAction::class)
        ->shouldReceive('handle')->once();

    $this->actingAs($user)
        ->post("/plan/{$workout->id}/move", [
            'date' => CarbonImmutable::now()->startOfWeek()->addDays(2)->toDateString(),
        ])
        ->assertRedirect();
});

it('does not let a non-owner move a session', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $workout = calendarWorkout($owner, CarbonImmutable::now()->toDateString());

    $this->actingAs($other)
        ->post("/plan/{$workout->id}/move", ['date' => CarbonImmutable::now()->addDay()->toDateString()])
        ->assertForbidden();
});
