<?php

declare(strict_types=1);

use App\Services\Training\PacingPlanService;

/** Flat course of `points` samples over `distance` metres at constant elevation. */
function flatProfile(int $distance = 2000, int $step = 100): array
{
    $profile = [];
    for ($d = 0; $d <= $distance; $d += $step) {
        $profile[] = ['dist' => (float) $d, 'ele' => 100.0];
    }

    return $profile;
}

it('splits a flat course into near-even paces summing to the target', function () {
    $plan = (new PacingPlanService)->plan(flatProfile(), 600, 1000);

    expect($plan['splits'])->toHaveCount(2)
        ->and($plan['total_seconds'])->toBe(600)
        ->and(abs($plan['splits'][0]['pace_per_km'] - $plan['splits'][1]['pace_per_km']))
        ->toBeLessThanOrEqual(2);
});

it('paces uphill slower and downhill faster at even effort', function () {
    // 1 km climbing +60 m, then 1 km descending -60 m.
    $profile = [];
    for ($d = 0; $d <= 1000; $d += 100) {
        $profile[] = ['dist' => (float) $d, 'ele' => 100.0 + $d * 0.06];
    }
    for ($d = 1100; $d <= 2000; $d += 100) {
        $profile[] = ['dist' => (float) $d, 'ele' => 160.0 - ($d - 1000) * 0.06];
    }

    $plan = (new PacingPlanService)->plan($profile, 600, 1000);

    expect($plan['splits'][0]['pace_per_km'])
        ->toBeGreaterThan($plan['splits'][1]['pace_per_km'])
        ->and($plan['splits'][0]['avg_grade'])->toBeGreaterThan(0);
});

it('slows the plan in the heat', function () {
    $service = new PacingPlanService;
    $cool = $service->plan(flatProfile(), 600, 1000);
    $hot = $service->plan(flatProfile(), 600, 1000, 35.0);

    expect($hot['total_seconds'])->toBeGreaterThan($cool['total_seconds'])
        ->and($hot['weather_factor'])->toBeGreaterThan(1.0);
});
