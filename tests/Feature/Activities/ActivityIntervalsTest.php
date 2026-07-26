<?php

declare(strict_types=1);

use App\Enums\Sport;
use App\Models\Activity;
use App\Models\User;
use Carbon\CarbonImmutable;

it('exposes scored intervals on the activity page', function () {
    $user = User::factory()->create();

    $laps = array_map(fn (float $speed): array => [
        'duration_s' => 120,
        'distance_m' => round($speed * 120, 1),
        'avg_speed_mps' => $speed,
        'avg_hr' => 158,
    ], [3.0, 5.0, 2.5, 5.0, 2.5, 5.0, 3.0]);

    $activity = Activity::create([
        'user_id' => $user->id,
        'external_id' => 'intervals-1',
        'sport' => Sport::Run,
        'started_at' => CarbonImmutable::now(),
        'laps' => $laps,
    ]);

    $this->actingAs($user)
        ->get("/activities/{$activity->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('activities/Show')
            ->where('intervals.structured', true)
            ->where('intervals.counts.work', 3)
            ->has('intervals.reps', 7)
            ->where('intervals.reps.1.verdict', 'hit'));
});

it('has no intervals payload for an activity without laps', function () {
    $user = User::factory()->create();
    $activity = Activity::create([
        'user_id' => $user->id,
        'external_id' => 'no-laps',
        'sport' => Sport::Run,
        'started_at' => CarbonImmutable::now(),
    ]);

    $this->actingAs($user)
        ->get("/activities/{$activity->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('activities/Show')
            ->where('intervals', null));
});
