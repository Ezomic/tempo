<?php

declare(strict_types=1);

use App\Enums\Sport;
use App\Models\Activity;
use App\Models\User;
use App\Services\Training\ThresholdTrendService;
use Carbon\CarbonImmutable;

function markerActivity(User $user, string $date, ?float $vo2max, ?array $meanMax): void
{
    Activity::create([
        'user_id' => $user->id,
        'external_id' => uniqid('mk_', true),
        'sport' => Sport::Run,
        'started_at' => CarbonImmutable::parse($date),
        'mean_max' => $meanMax,
        'raw_summary' => $vo2max === null ? [] : ['vO2MaxValue' => $vo2max],
    ]);
}

it('builds VO2max and derived threshold series', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-06-15');

    markerActivity($user, '2026-06-08', 52.0, [1800 => 3.8, 3600 => 3.5]);
    markerActivity($user, '2026-06-15', 54.0, [1800 => 4.0, 3600 => 3.6]);

    $trend = app(ThresholdTrendService::class)->trend($user, $today, 16);

    expect($trend['vo2max'])->toHaveCount(2)
        ->and($trend['vo2max'][1]['value'])->toBe(54.0)
        // Threshold speed is taken at ~1800 s: 4.0 m/s -> 250 s/km.
        ->and($trend['threshold'][1]['speed_mps'])->toBe(4.0)
        ->and($trend['threshold'][1]['pace_s_per_km'])->toBe(250);
});

it('omits VO2max weeks with no Garmin value but keeps threshold', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-06-15');

    markerActivity($user, '2026-06-15', null, [1800 => 3.9]);

    $trend = app(ThresholdTrendService::class)->trend($user, $today, 16);

    expect($trend['vo2max'])->toBe([])
        ->and($trend['threshold'])->toHaveCount(1);
});
