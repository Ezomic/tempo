<?php

declare(strict_types=1);

use App\Actions\PushPlannedWorkoutAction;
use App\Enums\Sport;
use App\Enums\WorkoutType;
use App\Models\PlannedWorkout;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function configureChronosSync(): void
{
    config([
        'services.chronos.url' => 'https://chronos.test',
        'services.chronos.token' => 'tok',
    ]);
}

function pushableWorkout(User $user, array $attributes = []): PlannedWorkout
{
    return PlannedWorkout::create(array_merge([
        'user_id' => $user->id,
        'date' => '2026-07-20',
        'sport' => Sport::Run,
        'workout_type' => WorkoutType::Easy,
        'title' => 'Easy run',
        'duration_min' => 45,
    ], $attributes));
}

it('creates a chronos event on first push', function () {
    configureChronosSync();
    Http::fake(['chronos.test/*' => Http::response(['id' => 'evt_1', 'url' => 'https://chronos.test/cal'], 201)]);

    $user = User::factory()->create();
    $workout = pushableWorkout($user);

    app(PushPlannedWorkoutAction::class)->handle($workout);

    expect($workout->refresh()->chronos_event_id)->toBe('evt_1');
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/events'));
});

it('updates the same event in place on a re-push instead of duplicating', function () {
    configureChronosSync();
    Http::fake(['chronos.test/*' => Http::response(['id' => 'evt_1', 'url' => 'https://chronos.test/cal'], 200)]);

    $user = User::factory()->create();
    $workout = pushableWorkout($user, [
        'chronos_event_id' => 'evt_1',
        'chronos_url' => 'https://chronos.test/cal',
        'pushed_at' => now(),
    ]);

    app(PushPlannedWorkoutAction::class)->handle($workout);

    expect($workout->refresh()->chronos_event_id)->toBe('evt_1');
    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains($request->url(), '/events/evt_1'));
    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
});

it('relocates the event when the session moves', function () {
    configureChronosSync();
    Http::fake(['chronos.test/*' => Http::response(['id' => 'evt_1', 'url' => 'https://chronos.test/cal'], 200)]);

    $user = User::factory()->create();
    $workout = pushableWorkout($user, [
        'date' => '2026-07-22',
        'chronos_event_id' => 'evt_1',
        'pushed_at' => now(),
    ]);

    app(PushPlannedWorkoutAction::class)->handle($workout);

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && $request['starts_at'] === '2026-07-22');
});

it('deletes the chronos event when the plan is removed', function () {
    configureChronosSync();
    Http::fake(['chronos.test/*' => Http::response([], 204)]);

    $user = User::factory()->create();
    $workout = pushableWorkout($user, [
        'chronos_event_id' => 'evt_1',
        'pushed_at' => now(),
    ]);

    $this->actingAs($user)
        ->delete("/plan/{$workout->id}")
        ->assertRedirect();

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), '/events/evt_1'));
    expect(PlannedWorkout::find($workout->id))->toBeNull();
});
