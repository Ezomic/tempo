<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WellnessDay;
use App\Services\Training\OvertrainingWatchService;
use Carbon\CarbonImmutable;

function otWellness(User $user, CarbonImmutable $date, int $rhr, int $hrv, float $sleepHours): void
{
    WellnessDay::create([
        'user_id' => $user->id,
        'date' => $date->toDateString(),
        'resting_hr' => $rhr,
        'hrv_last_night_ms' => $hrv,
        'sleep_duration_s' => (int) round($sleepHours * 3600),
    ]);
}

it('raises back off when several markers deteriorate', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-06-30');

    // Baseline: days 8-35 ago, healthy.
    for ($i = 8; $i <= 35; $i++) {
        otWellness($user, $today->subDays($i), 48, 65, 8.0);
    }
    // Recent: last 7 days, elevated RHR + suppressed HRV + short sleep.
    for ($i = 0; $i <= 6; $i++) {
        otWellness($user, $today->subDays($i), 56, 55, 6.0);
    }

    $result = app(OvertrainingWatchService::class)->watch($user, $today);

    expect($result)->not->toBeNull()
        ->and($result['level'])->toBe('back_off')
        ->and($result['reasons'])->toHaveCount(3);
});

it('stays quiet on normal variation', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-06-30');

    for ($i = 0; $i <= 35; $i++) {
        otWellness($user, $today->subDays($i), 48, 64, 7.8);
    }

    expect(app(OvertrainingWatchService::class)->watch($user, $today))->toBeNull();
});

it('flags a single marker as watch', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-06-30');

    for ($i = 8; $i <= 35; $i++) {
        otWellness($user, $today->subDays($i), 48, 65, 8.0);
    }
    // Only resting HR elevated; HRV and sleep fine.
    for ($i = 0; $i <= 6; $i++) {
        otWellness($user, $today->subDays($i), 55, 65, 8.0);
    }

    $result = app(OvertrainingWatchService::class)->watch($user, $today);

    expect($result['level'])->toBe('watch')
        ->and($result['reasons'])->toHaveCount(1);
});
