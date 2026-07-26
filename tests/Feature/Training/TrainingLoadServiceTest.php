<?php

declare(strict_types=1);

use App\Enums\Sport;
use App\Models\Activity;
use App\Models\User;
use App\Services\Training\TrainingLoadService;
use Carbon\CarbonImmutable;

function makeActivity(User $user, Sport $sport, string $date, float $trimp): void
{
    Activity::create([
        'user_id' => $user->id,
        'external_id' => uniqid('act_', true),
        'sport' => $sport,
        'started_at' => CarbonImmutable::parse($date),
        'trimp' => $trimp,
    ]);
}

it('computes acute vs chronic load and the ratio', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-07-15');

    makeActivity($user, Sport::Run, '2026-07-15', 50);   // today, acute
    makeActivity($user, Sport::Bike, '2026-07-13', 30);  // 2 days ago, acute
    makeActivity($user, Sport::Run, '2026-06-25', 40);   // 20 days ago, chronic only

    $load = (new TrainingLoadService)->acuteChronic($user, $today);

    // acute = 50 + 30 = 80; chronic 28d total = 120; weekly avg = 30; ratio = 2.67
    expect($load['acute'])->toBe(80.0)
        ->and($load['chronic_weekly'])->toBe(30.0)
        ->and($load['ratio'])->toBe(2.67)
        ->and($load['status'])->toBe('high');
});

it('returns a null ratio and unknown status without history', function () {
    $user = User::factory()->create();

    $load = (new TrainingLoadService)->acuteChronic($user, CarbonImmutable::parse('2026-07-15'));

    expect($load['ratio'])->toBeNull()
        ->and($load['status'])->toBe('unknown');
});

it('splits chronic load by sport and reconciles with the total', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-07-15');

    makeActivity($user, Sport::Run, '2026-07-15', 60);
    makeActivity($user, Sport::Bike, '2026-07-10', 40);
    makeActivity($user, Sport::Run, '2026-06-25', 20);

    $service = new TrainingLoadService;
    $split = $service->chronicBySport($user, $today);
    $combined = $service->acuteChronic($user, $today);

    // run = (60 + 20) / 4 = 20; bike = 40 / 4 = 10; total = 120 / 4 = 30.
    expect($split['run'])->toBe(20.0)
        ->and($split['bike'])->toBe(10.0)
        ->and($split['total'])->toBe(30.0)
        ->and($split['run'] + $split['bike'] + $split['other'])->toBe($split['total'])
        ->and($split['total'])->toBe($combined['chronic_weekly']);
});

it('buckets weekly load by sport', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-07-15'); // Wed; week starts Mon 2026-07-13

    makeActivity($user, Sport::Run, '2026-07-15', 50);
    makeActivity($user, Sport::Bike, '2026-07-13', 30);

    $weeks = (new TrainingLoadService)->weeklyBySport($user, $today, 8);

    expect($weeks)->toHaveCount(8);

    $current = end($weeks);
    expect($current['week_start'])->toBe('2026-07-13')
        ->and($current['run'])->toBe(50.0)
        ->and($current['bike'])->toBe(30.0)
        ->and($current['total'])->toBe(80.0);
});
