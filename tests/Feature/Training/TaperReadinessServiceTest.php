<?php

declare(strict_types=1);

use App\Enums\GoalType;
use App\Models\DailyLoadMetric;
use App\Models\TrainingGoal;
use App\Models\User;
use App\Services\Training\TaperReadinessService;
use Carbon\CarbonImmutable;

function raceGoal(User $user, CarbonImmutable $date): TrainingGoal
{
    return TrainingGoal::create([
        'user_id' => $user->id,
        'type' => GoalType::RaceTime,
        'target_value' => 1200,
        'distance_m' => 5000,
        'target_date' => $date->toDateString(),
    ]);
}

function loadDays(User $user, CarbonImmutable $today, float $latestTsb): void
{
    for ($i = 13; $i >= 0; $i--) {
        DailyLoadMetric::create([
            'user_id' => $user->id,
            'date' => $today->subDays($i)->toDateString(),
            'trimp' => $i <= 6 ? 40 : 100, // this week lighter than last week
            'ctl' => 50,
            'atl' => 40,
            'tsb' => $i === 0 ? $latestTsb : 5,
        ]);
    }
}

it('returns nothing without a race in the taper window', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-05-01');
    raceGoal($user, $today->addDays(40)); // well outside the window
    loadDays($user, $today, 15);

    expect(app(TaperReadinessService::class)->forNextRace($user, $today))->toBeNull();
});

it('flags a clean taper as on track', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-05-01');
    raceGoal($user, $today->addDays(5));
    loadDays($user, $today, 15); // TSB in the fresh window, load dropped

    $result = app(TaperReadinessService::class)->forNextRace($user, $today);

    $freshness = collect($result['factors'])->firstWhere('key', 'freshness');
    expect($result['verdict'])->not->toBe('off')
        ->and($freshness['state'])->toBe('good')
        ->and($result['days_to_race'])->toBe(5);
});

it('flags an under-tapered week as off', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-05-01');
    raceGoal($user, $today->addDays(3));
    loadDays($user, $today, -8); // still fatigued

    $result = app(TaperReadinessService::class)->forNextRace($user, $today);

    $freshness = collect($result['factors'])->firstWhere('key', 'freshness');
    expect($result['verdict'])->toBe('off')
        ->and($freshness['state'])->toBe('off');
});
