<?php

declare(strict_types=1);

use App\Enums\Sport;
use App\Models\Activity;
use App\Models\User;
use App\Services\Training\ZoneDistributionService;
use Carbon\CarbonImmutable;

/**
 * @param  array<int, int>  $zoneSeconds
 */
function zoneActivity(User $user, string $date, array $zoneSeconds): void
{
    Activity::create([
        'user_id' => $user->id,
        'external_id' => uniqid('act_', true),
        'sport' => Sport::Run,
        'started_at' => CarbonImmutable::parse($date),
        'hr_zone_seconds' => $zoneSeconds,
    ]);
}

it('sums weekly time-in-zone so zones reconcile with the total', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-07-15'); // Wed

    zoneActivity($user, '2026-07-15', [1 => 600, 2 => 1200, 3 => 300, 4 => 120, 5 => 60]);
    zoneActivity($user, '2026-07-13', [1 => 300, 2 => 300, 3 => 0, 4 => 0, 5 => 0]);

    $weeks = (new ZoneDistributionService)->weekly($user, $today, 8);
    $current = end($weeks);

    expect($current['zones'][1])->toBe(900)
        ->and($current['zones'][2])->toBe(1500)
        ->and($current['total'])->toBe(900 + 1500 + 300 + 120 + 60)
        ->and(array_sum($current['zones']))->toBe($current['total']);
});

it('computes the 80/20 split and an on-target verdict', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-07-15');

    // 8400s easy, 600s moderate, 1000s hard => 84% easy.
    zoneActivity($user, '2026-07-14', [1 => 4200, 2 => 4200, 3 => 600, 4 => 600, 5 => 400]);

    $polarization = (new ZoneDistributionService)->polarization($user, $today, 4);

    expect($polarization['easy_pct'])->toBe(84.0)
        ->and($polarization['verdict'])->toBe('on_target');
});

it('flags too much intensity when easy time is low', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-07-15');

    zoneActivity($user, '2026-07-14', [1 => 1000, 2 => 1000, 3 => 1000, 4 => 1000, 5 => 1000]);

    $polarization = (new ZoneDistributionService)->polarization($user, $today, 4);

    expect($polarization['easy_pct'])->toBe(40.0)
        ->and($polarization['verdict'])->toBe('too_much_intensity');
});

it('returns unknown polarization without zone data', function () {
    $user = User::factory()->create();

    $polarization = (new ZoneDistributionService)
        ->polarization($user, CarbonImmutable::parse('2026-07-15'), 4);

    expect($polarization['verdict'])->toBe('unknown')
        ->and($polarization['easy_pct'])->toBeNull();
});
