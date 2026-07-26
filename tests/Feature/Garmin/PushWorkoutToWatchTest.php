<?php

declare(strict_types=1);

use App\Enums\Intensity;
use App\Enums\Sport;
use App\Models\GarminConnection;
use App\Models\PlannedWorkout;
use App\Models\User;
use App\Services\Garmin\GarminWorkoutBuilder;
use Illuminate\Support\Facades\Http;

function connectedUser(): User
{
    $user = User::factory()->create();
    GarminConnection::create([
        'user_id' => $user->id,
        'status' => GarminConnection::STATUS_CONNECTED,
    ]);

    return $user;
}

function intervalWorkout(User $user): PlannedWorkout
{
    $workout = $user->plannedWorkouts()->create([
        'date' => '2026-07-28',
        'sport' => Sport::Run,
        'title' => 'Jog/walk builder',
        'duration_min' => 30,
    ]);

    $workout->steps()->createMany([
        ['position' => 0, 'repeat' => 1, 'duration_min' => 10, 'intensity' => Intensity::Easy, 'label' => 'Warm up'],
        ['position' => 1, 'repeat' => 6, 'duration_min' => 1, 'recovery_min' => 2, 'intensity' => Intensity::Hard, 'recovery_intensity' => Intensity::Recovery, 'label' => 'Jog'],
        ['position' => 2, 'repeat' => 1, 'duration_min' => 5, 'intensity' => Intensity::Easy, 'label' => 'Cool down'],
    ]);

    return $workout->load('steps');
}

it('maps steps into intervals and a repeat group, keeping labels', function () {
    $body = app(GarminWorkoutBuilder::class)->build(intervalWorkout(User::factory()->create()));

    expect($body['sport'])->toBe('running')
        ->and($body['name'])->toBe('Jog/walk builder')
        ->and($body['estimated_seconds'])->toBe(1980)
        ->and($body['steps'])->toHaveCount(3);

    [$warm, $repeat, $cool] = $body['steps'];

    expect($warm['type'])->toBe('interval')
        ->and($warm['seconds'])->toBe(600)
        ->and($warm['description'])->toBe('Warm up')
        ->and($repeat['type'])->toBe('repeat')
        ->and($repeat['iterations'])->toBe(6)
        ->and($repeat['steps'])->toHaveCount(2)
        ->and($repeat['steps'][0]['type'])->toBe('interval')
        ->and($repeat['steps'][0]['seconds'])->toBe(60)
        ->and($repeat['steps'][0]['description'])->toBe('Jog')
        ->and($repeat['steps'][1]['type'])->toBe('recovery')
        ->and($repeat['steps'][1]['seconds'])->toBe(120)
        ->and($repeat['steps'][1]['description'])->toBe('Walk')
        ->and($cool['type'])->toBe('interval')
        ->and($cool['seconds'])->toBe(300)
        ->and($cool['description'])->toBe('Cool down');

    // Run work intervals carry a pace target; walk recoveries do not.
    expect($warm)->toHaveKeys(['target_pace_low', 'target_pace_high'])
        ->and($repeat['steps'][0])->toHaveKeys(['target_pace_low', 'target_pace_high'])
        ->and($repeat['steps'][1])->not->toHaveKey('target_pace_low')
        ->and($repeat['steps'][0]['target_pace_low'])->toBeLessThan($repeat['steps'][0]['target_pace_high']);
});

it('gives bike intervals an HR zone target, not a pace target', function () {
    $user = User::factory()->create();
    $workout = $user->plannedWorkouts()->create([
        'date' => '2026-07-30', 'sport' => Sport::Bike, 'title' => 'Ride', 'duration_min' => 30,
    ]);
    $workout->steps()->create(['position' => 0, 'repeat' => 1, 'duration_min' => 30, 'intensity' => Intensity::Easy]);

    $steps = app(GarminWorkoutBuilder::class)->build($workout->load('steps'))['steps'];

    expect($steps[0])->not->toHaveKey('target_pace_low')
        ->and($steps[0]['target_hr_zone'])->toBe(2);
});

it('maps a single unlabelled block to one interval, not a warmup', function () {
    $user = User::factory()->create();
    $workout = $user->plannedWorkouts()->create([
        'date' => '2026-07-30', 'sport' => Sport::Bike, 'title' => 'Easy ride', 'duration_min' => 30,
    ]);
    $workout->steps()->create(['position' => 0, 'repeat' => 1, 'duration_min' => 30, 'intensity' => Intensity::Easy]);

    $steps = app(GarminWorkoutBuilder::class)->build($workout->load('steps'))['steps'];

    expect($steps)->toHaveCount(1)
        ->and($steps[0]['type'])->toBe('interval')
        ->and($steps[0]['description'])->toBe('Ride');
});

it('defaults run step wording to Jog and Walk', function () {
    $user = User::factory()->create();
    $workout = $user->plannedWorkouts()->create([
        'date' => '2026-07-28', 'sport' => Sport::Run, 'title' => 'Intervals', 'duration_min' => 20,
    ]);
    $workout->steps()->create([
        'position' => 0, 'repeat' => 4, 'duration_min' => 2, 'recovery_min' => 1,
        'intensity' => Intensity::Hard, 'recovery_intensity' => Intensity::Recovery,
    ]);

    $repeat = app(GarminWorkoutBuilder::class)->build($workout->load('steps'))['steps'][0];

    expect($repeat['steps'][0]['description'])->toBe('Jog')
        ->and($repeat['steps'][1]['description'])->toBe('Walk');
});

it('defaults bike step wording to Ride and Easy spin', function () {
    $user = User::factory()->create();
    $workout = $user->plannedWorkouts()->create([
        'date' => '2026-07-30', 'sport' => Sport::Bike, 'title' => 'Ride', 'duration_min' => 30,
    ]);
    $workout->steps()->create([
        'position' => 0, 'repeat' => 3, 'duration_min' => 3, 'recovery_min' => 2,
        'intensity' => Intensity::Steady, 'recovery_intensity' => Intensity::Easy,
    ]);

    $repeat = app(GarminWorkoutBuilder::class)->build($workout->load('steps'))['steps'][0];

    expect($repeat['steps'][0]['description'])->toBe('Ride')
        ->and($repeat['steps'][1]['description'])->toBe('Easy spin');
});

it('maps bike workouts to cycling', function () {
    $user = User::factory()->create();
    $workout = $user->plannedWorkouts()->create([
        'date' => '2026-07-28', 'sport' => Sport::Bike, 'title' => 'Ride', 'duration_min' => 40,
    ]);
    $workout->steps()->create(['position' => 0, 'repeat' => 1, 'duration_min' => 40, 'intensity' => Intensity::Easy]);

    expect(app(GarminWorkoutBuilder::class)->build($workout->load('steps'))['sport'])->toBe('cycling');
});

it('sends a workout to the watch and stamps it', function () {
    config(['services.garmin_sidecar.url' => 'http://sidecar.test', 'services.garmin_sidecar.secret' => 's']);
    Http::fake(['sidecar.test/*' => Http::response(['workout_id' => 987], 200)]);

    $user = connectedUser();
    $workout = intervalWorkout($user);

    $this->actingAs($user)
        ->post("/plan/{$workout->id}/watch")
        ->assertRedirect();

    $workout->refresh();
    expect($workout->isOnWatch())->toBeTrue()
        ->and($workout->garmin_workout_id)->toBe('987');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/workouts')
        && $request['sport'] === 'running'
        && $request['date'] === '2026-07-28'
        && count($request['steps']) === 3);
});

it('forbids sending another user workout to the watch', function () {
    $workout = intervalWorkout(connectedUser());

    $this->actingAs(connectedUser())
        ->post("/plan/{$workout->id}/watch")
        ->assertForbidden();
});

it('shows an error when garmin is not connected', function () {
    $user = User::factory()->create();
    $workout = intervalWorkout($user);

    $this->actingAs($user)
        ->post("/plan/{$workout->id}/watch")
        ->assertSessionHasErrors('watch');
});
