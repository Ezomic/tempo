<?php

declare(strict_types=1);

use App\Enums\GoalType;
use App\Enums\Sport;
use App\Models\Activity;
use App\Models\DailyLoadMetric;
use App\Models\MeanMaxEffort;
use App\Models\TrainingGoal;
use App\Models\User;
use App\Services\Training\GoalProgressService;
use Carbon\CarbonImmutable;

function ctlSeries(User $user, CarbonImmutable $today, float $start, float $end, int $days = 21): void
{
    for ($i = $days; $i >= 0; $i--) {
        $t = $i / $days;
        DailyLoadMetric::create([
            'user_id' => $user->id,
            'date' => $today->subDays($i)->toDateString(),
            'trimp' => 0,
            'ctl' => $start + ($end - $start) * (1 - $t),
            'atl' => 0,
            'tsb' => 0,
        ]);
    }
}

it('marks a CTL goal ahead when already past target', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-03-01');
    ctlSeries($user, $today, 40, 55);

    $goal = TrainingGoal::create([
        'user_id' => $user->id,
        'type' => GoalType::Ctl,
        'target_value' => 50,
        'target_date' => $today->addWeeks(8)->toDateString(),
    ]);

    $result = app(GoalProgressService::class)->evaluate($goal, $user, $today);

    expect($result['status'])->toBe('ahead')
        ->and($result['current'])->toBe(55.0);
});

it('marks a rising CTL goal on track and a flat one behind', function () {
    $today = CarbonImmutable::parse('2026-03-01');

    $rising = User::factory()->create();
    ctlSeries($rising, $today, 40, 50); // +0.5/day
    $risingGoal = TrainingGoal::create([
        'user_id' => $rising->id,
        'type' => GoalType::Ctl,
        'target_value' => 60,
        'target_date' => $today->addDays(30)->toDateString(),
    ]);

    $flat = User::factory()->create();
    ctlSeries($flat, $today, 50, 50); // no growth
    $flatGoal = TrainingGoal::create([
        'user_id' => $flat->id,
        'type' => GoalType::Ctl,
        'target_value' => 60,
        'target_date' => $today->addDays(30)->toDateString(),
    ]);

    $service = app(GoalProgressService::class);

    expect($service->evaluate($risingGoal, $rising, $today)['status'])->toBe('on_track')
        ->and($service->evaluate($flatGoal, $flat, $today)['status'])->toBe('behind');
});

it('evaluates a race-time goal against the mean-max curve', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-03-01');
    ctlSeries($user, $today, 45, 45);

    $activity = Activity::create([
        'user_id' => $user->id,
        'external_id' => 'race-goal-act',
        'sport' => Sport::Run,
        'started_at' => $today->toDateString(),
    ]);

    foreach ([60, 300, 600, 1200, 1800, 3600] as $duration) {
        MeanMaxEffort::create([
            'user_id' => $user->id,
            'sport' => Sport::Run,
            'duration_s' => $duration,
            'speed_mps' => 4.0,
            'activity_id' => $activity->id,
            'achieved_on' => $today->toDateString(),
        ]);
    }

    // A 4 m/s runner cannot yet break 20:00 for 5K (~1250s predicted).
    $tough = TrainingGoal::create([
        'user_id' => $user->id,
        'type' => GoalType::RaceTime,
        'target_value' => 1200,
        'distance_m' => 5000,
        'target_date' => $today->addDays(2)->toDateString(),
    ]);

    // But comfortably beats 30:00.
    $easy = TrainingGoal::create([
        'user_id' => $user->id,
        'type' => GoalType::RaceTime,
        'target_value' => 1800,
        'distance_m' => 5000,
        'target_date' => $today->addDays(2)->toDateString(),
    ]);

    $service = app(GoalProgressService::class);

    expect($service->evaluate($tough, $user, $today)['status'])->toBe('behind')
        ->and($service->evaluate($easy, $user, $today)['status'])->toBe('ahead');
});
