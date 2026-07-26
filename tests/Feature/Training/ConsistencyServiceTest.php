<?php

declare(strict_types=1);

use App\Enums\Sport;
use App\Models\Activity;
use App\Models\User;
use App\Services\Training\ConsistencyService;
use Carbon\CarbonImmutable;

function trainingDay(User $user, string $date, ?float $trimp): void
{
    Activity::create([
        'user_id' => $user->id,
        'external_id' => uniqid('cons_', true),
        'sport' => Sport::Run,
        'started_at' => CarbonImmutable::parse($date),
        'trimp' => $trimp,
        'duration_s' => 1800,
    ]);
}

it('counts the current streak including today', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-06-30');
    trainingDay($user, '2026-06-28', 50);
    trainingDay($user, '2026-06-29', 60);
    trainingDay($user, '2026-06-30', 40);

    $result = app(ConsistencyService::class)->heatmap($user, $today);

    expect($result['current_streak'])->toBe(3)
        ->and($result['longest_streak'])->toBe(3)
        ->and($result['active_days'])->toBe(3);
});

it('keeps the current streak when today is a rest day', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-06-30');
    trainingDay($user, '2026-06-28', 50);
    trainingDay($user, '2026-06-29', 60);
    // Nothing today.

    expect(app(ConsistencyService::class)->heatmap($user, $today)['current_streak'])
        ->toBe(2);
});

it('buckets day load into heatmap levels', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-06-30');
    trainingDay($user, '2026-06-30', 120); // level 4
    trainingDay($user, '2026-06-29', 45); // level 2

    $days = collect(app(ConsistencyService::class)->heatmap($user, $today)['days'])
        ->keyBy('date');

    expect($days['2026-06-30']['level'])->toBe(4)
        ->and($days['2026-06-29']['level'])->toBe(2)
        ->and($days['2026-06-25']['level'])->toBe(0);
});
