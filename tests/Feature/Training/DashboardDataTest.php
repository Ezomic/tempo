<?php

declare(strict_types=1);

use App\Enums\HrvStatus;
use App\Enums\Sport;
use App\Enums\WorkoutType;
use App\Models\Activity;
use App\Models\PlannedWorkout;
use App\Models\User;
use App\Models\WellnessDay;
use App\Services\Training\FitnessCurveService;
use Carbon\CarbonImmutable;
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
            ->has('guardrails.status')
            ->has('guardrails.acwr_band')
            ->has('guardrails.message')
            ->has('recentActivities', 1)
            ->where('todayPlan', null)
            ->where('readiness.hrv_status', 'balanced')
            // Balanced HRV + healthy battery, but a single activity spikes the
            // acute:chronic ratio, so the score is docked from 100.
            ->where('readiness.score', 75));
});

it('flags a recent activity that overran a planned easy day', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::now();

    Activity::create([
        'user_id' => $user->id,
        'external_id' => 'hard-easy',
        'sport' => Sport::Run,
        'started_at' => $today,
        'trimp' => 90,
    ]);
    PlannedWorkout::create([
        'user_id' => $user->id,
        'date' => $today->toDateString(),
        'sport' => Sport::Run,
        'workout_type' => WorkoutType::Easy,
        'title' => 'Easy run',
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('recentActivities.0.recovery_flag', true)
            ->has('chronicBySport.total'));
});

it('exposes the stored fitness curve with history and projection', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::now();

    Activity::create([
        'user_id' => $user->id,
        'external_id' => 'a1',
        'sport' => Sport::Run,
        'started_at' => $today->subDays(3),
        'trimp' => 60,
    ]);

    (new FitnessCurveService)->recompute($user, $today);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('fitnessCurve.current')
            ->has('fitnessCurve.history', 4) // 3 days ago through today
            ->has('fitnessCurve.projection', 14)
            ->has('fitnessCurve.history.0', fn (Assert $point) => $point
                ->has('date')
                ->has('ctl')
                ->has('atl')
                ->has('tsb')));
});
