<?php

declare(strict_types=1);

use App\Enums\Sport;
use App\Models\User;
use App\Models\WorkoutTemplate;
use Carbon\CarbonImmutable;

function templatePayload(): array
{
    return [
        'name' => '5x1000m threshold',
        'sport' => 'run',
        'workout_type' => 'intervals',
        'steps' => [
            ['repeat' => 1, 'intensity' => 'easy', 'duration_min' => 15],
            [
                'repeat' => 5,
                'intensity' => 'hard',
                'duration_min' => 4,
                'recovery_min' => 2,
                'recovery_intensity' => 'recovery',
                'label' => '1K rep',
            ],
            ['repeat' => 1, 'intensity' => 'easy', 'duration_min' => 10],
        ],
    ];
}

it('creates a workout template', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/workouts', templatePayload())->assertRedirect();

    $template = $user->workoutTemplates()->first();
    expect($template)->not->toBeNull()
        ->and($template->steps)->toHaveCount(3);
});

it('validates that steps are present', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/workouts', [
        'name' => 'Empty',
        'sport' => 'run',
        'steps' => [],
    ])->assertSessionHasErrors('steps');
});

it('materialises a planned workout with steps when applied', function () {
    $user = User::factory()->create();
    $template = WorkoutTemplate::create([
        'user_id' => $user->id,
        'name' => '5x1000m threshold',
        'sport' => Sport::Run,
        'workout_type' => 'intervals',
        'steps' => templatePayload()['steps'],
    ]);

    $date = CarbonImmutable::now()->addDays(3)->toDateString();

    $this->actingAs($user)
        ->post("/workouts/{$template->id}/apply", ['date' => $date])
        ->assertRedirect(route('plan.index'));

    $workout = $user->plannedWorkouts()->with('steps')->first();
    expect($workout)->not->toBeNull()
        ->and($workout->title)->toBe('5x1000m threshold')
        ->and($workout->steps)->toHaveCount(3);
});

it('does not mutate applied sessions when the template changes', function () {
    $user = User::factory()->create();
    $template = WorkoutTemplate::create([
        'user_id' => $user->id,
        'name' => 'Original',
        'sport' => Sport::Run,
        'workout_type' => null,
        'steps' => [['repeat' => 1, 'intensity' => 'easy', 'duration_min' => 20]],
    ]);

    $this->actingAs($user)
        ->post("/workouts/{$template->id}/apply", ['date' => CarbonImmutable::now()->addDay()->toDateString()])
        ->assertRedirect();

    $template->update(['name' => 'Renamed']);

    expect($user->plannedWorkouts()->first()->title)->toBe('Original');
});
