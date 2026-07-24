<?php

declare(strict_types=1);

use App\Enums\Sport;
use App\Models\PlannedWorkout;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function plannedFor(User $user, array $overrides = []): PlannedWorkout
{
    return $user->plannedWorkouts()->create(array_merge([
        'date' => '2026-07-25',
        'sport' => Sport::Run,
        'title' => 'Easy 40',
        'duration_min' => 40,
    ], $overrides));
}

function configureChronos(): void
{
    config([
        'services.chronos.url' => 'https://chronos.test/api',
        'services.chronos.token' => 'tok',
    ]);
}

it('renders the plan page with the chronos config state', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/plan')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Plan')
            ->where('chronosConfigured', false));
});

it('stores a planned workout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/plan', ['date' => '2026-07-25', 'sport' => 'run', 'title' => 'Tempo 5k', 'duration_min' => 30])
        ->assertRedirect();

    expect($user->plannedWorkouts()->count())->toBe(1);
});

it('pushes a workout to chronos and records the event', function () {
    configureChronos();
    Http::fake(['chronos.test/*' => Http::response(['id' => 'evt_1', 'url' => 'https://chronos.test/cal'], 201)]);

    $user = User::factory()->create();
    $workout = plannedFor($user, ['title' => 'Easy 40', 'sport' => Sport::Run, 'duration_min' => 40, 'date' => '2026-07-25']);

    $this->actingAs($user)->post("/plan/{$workout->id}/push")->assertRedirect();

    $workout->refresh();
    expect($workout->pushed_at)->not->toBeNull()
        ->and($workout->chronos_event_id)->toBe('evt_1')
        ->and($workout->chronos_url)->toBe('https://chronos.test/cal');

    Http::assertSent(fn ($request) => $request->url() === 'https://chronos.test/api/events'
        && $request['title'] === 'Run: Easy 40 (40 min)'
        && $request['all_day'] === true
        && $request['starts_at'] === '2026-07-25'
        && $request['source']['app'] === 'tempo'
        && $request['source']['type'] === 'planned-workout'
        && $request['source']['id'] === (string) $workout->id
        && $request->hasHeader('Authorization', 'Bearer tok'));
});

it('surfaces an error and does not mark pushed when chronos fails', function () {
    configureChronos();
    Http::fake(['chronos.test/*' => Http::response([], 500)]);

    $user = User::factory()->create();
    $workout = plannedFor($user);

    $this->actingAs($user)
        ->post("/plan/{$workout->id}/push")
        ->assertSessionHasErrors('push');

    expect($workout->refresh()->pushed_at)->toBeNull();
});

it('forbids pushing another user workout', function () {
    $workout = plannedFor(User::factory()->create());

    $this->actingAs(User::factory()->create())
        ->post("/plan/{$workout->id}/push")
        ->assertForbidden();
});

it('deletes a planned workout', function () {
    $user = User::factory()->create();
    $workout = plannedFor($user);

    $this->actingAs($user)->delete("/plan/{$workout->id}")->assertRedirect();

    expect(PlannedWorkout::query()->count())->toBe(0);
});

it('stores a workout with structured steps and derives the duration', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/plan', [
            'date' => '2026-07-28',
            'sport' => 'run',
            'workout_type' => 'intervals',
            'title' => 'Threshold reps',
            'steps' => [
                ['repeat' => 1, 'intensity' => 'easy', 'duration_min' => 15, 'label' => 'Warm up'],
                ['repeat' => 5, 'intensity' => 'hard', 'duration_min' => 3, 'recovery_min' => 2, 'recovery_intensity' => 'easy'],
                ['repeat' => 1, 'intensity' => 'easy', 'duration_min' => 10, 'label' => 'Cool down'],
            ],
        ])
        ->assertRedirect();

    $workout = $user->plannedWorkouts()->with('steps')->first();

    expect($workout->steps)->toHaveCount(3)
        ->and($workout->workout_type->value)->toBe('intervals')
        // 15 + 5*(3+2) + 10
        ->and($workout->duration_min)->toBe(50)
        ->and($workout->steps[1]->intensity->value)->toBe('hard')
        ->and($workout->steps[1]->position)->toBe(1);
});

it('exposes step + intensity metadata and reference options on the plan page', function () {
    $user = User::factory()->create();
    $workout = plannedFor($user, ['duration_min' => null]);
    $workout->steps()->create([
        'position' => 0,
        'repeat' => 5,
        'intensity' => 'hard',
        'duration_min' => 3,
        'recovery_min' => 2,
    ]);

    $this->actingAs($user)
        ->get('/plan')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Plan')
            ->has('intensityOptions', 5)
            ->has('workoutTypeOptions')
            ->where('workouts.0.steps.0.intensity.zone', 4)
            ->where('workouts.0.steps.0.intensity.hr_percent', '80-90%')
            ->where('workouts.0.steps.0.repeat', 5)
            ->where('workouts.0.description', fn (string $d) => str_contains($d, 'Hard') && str_contains($d, '80-90%')));
});

it('pushes a step-based description to chronos', function () {
    configureChronos();
    Http::fake(['chronos.test/*' => Http::response(['id' => 'evt_2', 'url' => 'https://chronos.test/cal'], 201)]);

    $user = User::factory()->create();
    $workout = plannedFor($user, ['duration_min' => null]);
    $workout->steps()->create([
        'position' => 0,
        'repeat' => 5,
        'intensity' => 'hard',
        'duration_min' => 3,
        'recovery_min' => 2,
        'recovery_intensity' => 'easy',
    ]);

    $this->actingAs($user)->post("/plan/{$workout->id}/push")->assertRedirect();

    Http::assertSent(fn ($request) => $request->url() === 'https://chronos.test/api/events'
        && str_contains($request['description'], '5 x 3 min Hard (Z4, ~80-90% max HR)')
        && str_contains($request['description'], 'with 2 min Easy between'));
});
