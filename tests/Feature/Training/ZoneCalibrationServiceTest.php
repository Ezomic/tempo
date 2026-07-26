<?php

declare(strict_types=1);

use App\Enums\Sport;
use App\Models\Activity;
use App\Models\HrZoneSettings;
use App\Models\User;
use App\Services\Training\ZoneCalibrationService;
use Carbon\CarbonImmutable;

function hardEffort(User $user, int $avgHr, int $durationS = 1800): void
{
    Activity::create([
        'user_id' => $user->id,
        'external_id' => uniqid('hard_', true),
        'sport' => Sport::Run,
        'started_at' => CarbonImmutable::now()->subDays(3),
        'duration_s' => $durationS,
        'avg_hr' => $avgHr,
    ]);
}

it('suggests calibration when the stored LTHR is stale', function () {
    $user = User::factory()->create();
    HrZoneSettings::create(['user_id' => $user->id, 'lthr' => 150]);
    hardEffort($user, 172);

    $suggestion = app(ZoneCalibrationService::class)->suggestion($user, CarbonImmutable::now());

    expect($suggestion)->not->toBeNull()
        ->and($suggestion['estimated_lthr'])->toBe(172)
        ->and($suggestion['current_lthr'])->toBe(150)
        ->and($suggestion['proposed_boundaries'])->toBe([146, 155, 163, 172]);
});

it('stays quiet when the stored LTHR already matches', function () {
    $user = User::factory()->create();
    HrZoneSettings::create(['user_id' => $user->id, 'lthr' => 170]);
    hardEffort($user, 171);

    expect(app(ZoneCalibrationService::class)->suggestion($user, CarbonImmutable::now()))
        ->toBeNull();
});

it('returns nothing without any sustained hard effort', function () {
    $user = User::factory()->create();

    expect(app(ZoneCalibrationService::class)->suggestion($user, CarbonImmutable::now()))
        ->toBeNull();
});

it('applies the calibration only on confirmation', function () {
    $user = User::factory()->create();
    HrZoneSettings::create(['user_id' => $user->id, 'lthr' => 150]);
    hardEffort($user, 172);

    $this->actingAs($user)->post('/zones/calibrate')->assertRedirect();

    $settings = $user->hrZoneSettings()->first();
    expect($settings->lthr)->toBe(172)
        ->and($settings->zone_boundaries)->toBe([146, 155, 163, 172]);
});
