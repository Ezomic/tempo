<?php

declare(strict_types=1);

use App\Enums\HrvStatus;
use App\Enums\Sport;
use App\Models\Activity;
use App\Models\User;
use App\Models\WellnessDay;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the empty state when there is no data', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('hasData', false)
            ->where('readiness', null));
});

it('exposes load, weekly and readiness props once data exists', function () {
    $user = User::factory()->create();

    Activity::create([
        'user_id' => $user->id,
        'external_id' => 'a1',
        'sport' => Sport::Run,
        'started_at' => now(),
        'trimp' => 50,
    ]);
    WellnessDay::create([
        'user_id' => $user->id,
        'date' => now()->toDateString(),
        'hrv_status' => HrvStatus::Balanced,
        'body_battery_high' => 80,
        'resting_hr' => 48,
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('hasData', true)
            ->has('weekly', 8)
            ->has('load.acute')
            ->has('load.ratio')
            ->where('readiness.hrv_status', 'balanced'));
});
