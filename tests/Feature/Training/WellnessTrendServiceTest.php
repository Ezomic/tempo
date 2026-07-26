<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WellnessDay;
use App\Services\Training\WellnessTrendService;
use Carbon\CarbonImmutable;

it('builds a daily trend with rolling baselines and keeps gaps as null', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-06-30');

    // Two days of data with a one-day gap between them.
    WellnessDay::create([
        'user_id' => $user->id,
        'date' => $today->subDays(2)->toDateString(),
        'sleep_duration_s' => 7 * 3600,
        'hrv_last_night_ms' => 60,
        'resting_hr' => 48,
    ]);
    WellnessDay::create([
        'user_id' => $user->id,
        'date' => $today->toDateString(),
        'sleep_duration_s' => 8 * 3600,
        'hrv_last_night_ms' => 70,
        'resting_hr' => 46,
    ]);

    $points = app(WellnessTrendService::class)->trend($user, $today, 3);

    expect($points)->toHaveCount(3)
        ->and($points[0]['hrv'])->toBe(60)
        ->and($points[1]['hrv'])->toBeNull()        // the gap day
        ->and($points[2]['hrv'])->toBe(70)
        ->and($points[2]['sleep_hours'])->toBe(8.0)
        // Baseline on the last day averages the two present HRV values.
        ->and($points[2]['baseline_hrv'])->toBe(65.0);
});

it('renders the wellness page', function () {
    $this->actingAs(User::factory()->create())->get('/wellness')->assertOk();
});
