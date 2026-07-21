<?php

declare(strict_types=1);

use App\Enums\Sport;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function activityFor(User $user, array $overrides = []): Activity
{
    return Activity::create(array_merge([
        'user_id' => $user->id,
        'external_id' => uniqid('act_', true),
        'sport' => Sport::Run,
        'started_at' => now(),
        'distance_m' => 10000,
        'duration_s' => 3000,
        'avg_hr' => 150,
        'trimp' => 60,
    ], $overrides));
}

it('lists only the current user activities', function () {
    $user = User::factory()->create();
    activityFor($user);
    activityFor($user);
    activityFor(User::factory()->create()); // someone else

    $this->actingAs($user)
        ->get('/activities')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('activities/Index')
            ->has('activities.data', 2));
});

it('shows an activity detail with its zone breakdown', function () {
    $user = User::factory()->create();
    $activity = activityFor($user, ['hr_zone_seconds' => [1 => 100, 2 => 200, 3 => 300]]);

    $this->actingAs($user)
        ->get("/activities/{$activity->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('activities/Show')
            ->where('activity.id', $activity->id)
            ->where('activity.hr_zone_seconds.3', 300));
});

it('forbids viewing another user activity', function () {
    $activity = activityFor(User::factory()->create());

    $this->actingAs(User::factory()->create())
        ->get("/activities/{$activity->id}")
        ->assertForbidden();
});

it('exposes precomputed streams when a streams file exists', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $path = "garmin/streams/{$user->id}/a1.json";
    Storage::disk('local')->put($path, (string) json_encode([
        't' => [0, 1], 'hr' => [140, 145], 'speed' => [3.0, 3.1], 'lat' => null, 'lng' => null,
    ]));
    $activity = activityFor($user, ['streams_path' => $path]);

    $this->actingAs($user)
        ->get("/activities/{$activity->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('activities/Show')
            ->where('streams.hr', [140, 145]));
});

it('has null streams when none are stored', function () {
    $user = User::factory()->create();
    $activity = activityFor($user);

    $this->actingAs($user)
        ->get("/activities/{$activity->id}")
        ->assertInertia(fn (Assert $page) => $page->where('streams', null));
});
