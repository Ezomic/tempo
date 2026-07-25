<?php

declare(strict_types=1);

use App\Enums\Sport;
use App\Enums\WorkoutType;
use App\Models\Activity;
use App\Models\PlannedWorkout;
use App\Models\User;
use App\Services\Routing\HomeLocationService;
use App\Services\Routing\PaceEstimator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function orsLoopResponse(): array
{
    return ['features' => [[
        'geometry' => ['coordinates' => [[5.1, 52.0], [5.11, 52.01]]],
        'properties' => ['summary' => ['distance' => 8000.0], 'ascent' => 30.0, 'descent' => 30.0],
    ]]];
}

function userWithHome(): User
{
    return User::factory()->create(['home_lat' => 52.09, 'home_lng' => 5.12]);
}

function workoutFor(User $user, array $overrides = []): PlannedWorkout
{
    return $user->plannedWorkouts()->create(array_merge([
        'date' => '2026-07-28',
        'sport' => Sport::Run,
        'title' => 'Easy 40',
        'duration_min' => 40,
    ], $overrides));
}

it('refuses to suggest when routing is not configured', function () {
    config(['services.ors.key' => null]);
    $user = userWithHome();
    $workout = workoutFor($user);

    $this->actingAs($user)
        ->postJson("/plan/{$workout->id}/route/suggest")
        ->assertStatus(422);
});

it('refuses to suggest when home is not set', function () {
    config(['services.ors.key' => 'k']);
    $user = User::factory()->create(['home_lat' => null, 'home_lng' => null]);
    $workout = workoutFor($user);

    $this->actingAs($user)
        ->postJson("/plan/{$workout->id}/route/suggest")
        ->assertStatus(422);
});

it('suggests a loop and returns the geometry', function () {
    config(['services.ors.key' => 'k']);
    Http::fake(['*' => Http::response(orsLoopResponse(), 200)]);
    $user = userWithHome();
    $workout = workoutFor($user);

    $this->actingAs($user)
        ->postJson("/plan/{$workout->id}/route/suggest", ['mode' => 'loop', 'seed' => 7])
        ->assertOk()
        ->assertJsonPath('kind', 'loop')
        ->assertJsonPath('mode', 'loop')
        ->assertJsonPath('distance_m', 8000);
});

it('honours a distance override', function () {
    config(['services.ors.key' => 'k']);
    Http::fake(['*' => Http::response(orsLoopResponse(), 200)]);
    $user = userWithHome();
    $workout = workoutFor($user);

    $this->actingAs($user)
        ->postJson("/plan/{$workout->id}/route/suggest", ['mode' => 'loop', 'distance_m' => 4000])
        ->assertOk();

    Http::assertSent(fn ($request) => ($request['options']['round_trip']['length'] ?? null) === 4000);
});

it('defaults to intervals mode for an intervals workout', function () {
    config(['services.ors.key' => 'k']);
    Http::fake(['*' => Http::response(orsLoopResponse(), 200)]);
    $user = userWithHome();
    $workout = workoutFor($user, ['workout_type' => WorkoutType::Intervals]);

    $this->actingAs($user)
        ->postJson("/plan/{$workout->id}/route/suggest")
        ->assertOk()
        ->assertJsonPath('mode', 'intervals')
        ->assertJsonPath('kind', 'out_and_back');
});

it('saves a route onto the workout', function () {
    $user = userWithHome();
    $workout = workoutFor($user);

    $this->actingAs($user)
        ->postJson("/plan/{$workout->id}/route", [
            'coordinates' => [[52.0, 5.1], [52.01, 5.11]],
            'distance_m' => 8000,
            'ascent_m' => 30,
            'kind' => 'loop',
        ])
        ->assertOk();

    $workout->refresh();
    expect($workout->hasRoute())->toBeTrue()
        ->and($workout->route_distance_m)->toBe(8000)
        ->and($workout->route_kind)->toBe('loop');
});

it('forbids suggesting for another user workout', function () {
    config(['services.ors.key' => 'k']);
    $workout = workoutFor(userWithHome());

    $this->actingAs(userWithHome())
        ->postJson("/plan/{$workout->id}/route/suggest")
        ->assertForbidden();
});

it('estimates distance from recent pace', function () {
    $user = User::factory()->create();
    Activity::create([
        'user_id' => $user->id, 'external_id' => 'a', 'sport' => Sport::Run,
        'started_at' => now(), 'avg_speed_mps' => 4.0,
    ]);

    // 30 min at 4 m/s = 7200 m.
    expect(app(PaceEstimator::class)->metersFor($user, Sport::Run, 30))->toBe(7200);
});

it('falls back to a default pace without history', function () {
    $user = User::factory()->create();

    // 30 min at the 2.5 m/s beginner run default = 4500 m.
    expect(app(PaceEstimator::class)->metersFor($user, Sport::Run, 30))->toBe(4500);
});

it('infers home from the median activity start point', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    foreach ([[52.0, 5.0], [52.1, 5.1], [52.2, 5.2]] as $i => [$lat, $lng]) {
        Storage::disk('local')->put("streams/{$i}.json", json_encode(['lat' => [$lat], 'lng' => [$lng]]));
        Activity::create([
            'user_id' => $user->id, 'external_id' => "a{$i}", 'sport' => Sport::Run,
            'started_at' => now()->subDays($i), 'streams_path' => "streams/{$i}.json",
        ]);
    }

    $home = app(HomeLocationService::class)->infer($user);

    expect($home)->not->toBeNull()
        ->and($home->lat)->toBe(52.1)
        ->and($home->lng)->toBe(5.1);
});
