<?php

declare(strict_types=1);

use App\Models\HrZoneSettings;
use App\Services\Garmin\TrimpCalculator;

function hrStream(int $bpm, int $seconds): array
{
    $samples = [];
    for ($t = 0; $t <= $seconds; $t++) {
        $samples[1_700_000_000 + $t] = $bpm;
    }

    return $samples;
}

it('computes Banister TRIMP from a steady heart-rate stream', function () {
    $settings = new HrZoneSettings(['max_hr' => 190, 'resting_hr' => 50, 'sex' => 'male']);

    // 60s at 150 bpm, hrr = (150-50)/140 = 0.714; one minute of load.
    $trimp = (new TrimpCalculator)->trimp(hrStream(150, 60), $settings);

    expect($trimp)->toBeGreaterThan(1.7)->toBeLessThan(1.9);
});

it('gives a higher TRIMP for a harder effort', function () {
    $settings = new HrZoneSettings(['max_hr' => 190, 'resting_hr' => 50, 'sex' => 'male']);
    $calc = new TrimpCalculator;

    $easy = $calc->trimp(hrStream(120, 60), $settings);
    $hard = $calc->trimp(hrStream(175, 60), $settings);

    expect($hard)->toBeGreaterThan($easy);
});

it('returns null without a heart-rate range', function () {
    $settings = new HrZoneSettings(['sex' => 'male']);

    expect((new TrimpCalculator)->trimp(hrStream(150, 60), $settings))->toBeNull();
});

it('buckets seconds into zones from max-HR defaults', function () {
    $settings = new HrZoneSettings(['max_hr' => 190, 'resting_hr' => 50, 'sex' => 'male']);

    // Defaults: 60/70/80/90% of 190 = 114/133/152/171. 150 bpm sits in zone 3.
    $zones = (new TrimpCalculator)->zoneSeconds(hrStream(150, 60), $settings);

    expect($zones[3])->toBe(60)
        ->and($zones[1])->toBe(0)
        ->and($zones[5])->toBe(0);
});
