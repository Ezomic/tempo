<?php

declare(strict_types=1);

use App\Services\Training\EfficiencyFactorAnalyzer;

/**
 * @return array{0: array<int, int>, 1: array<int, float>}
 */
function efStreams(int $seconds, float $speed, int $hr): array
{
    $hrs = [];
    $spd = [];
    for ($t = 0; $t <= $seconds; $t++) {
        $ts = 1_700_000_000 + $t;
        $hrs[$ts] = $hr;
        $spd[$ts] = $speed;
    }

    return [$hrs, $spd];
}

it('computes speed-per-heartbeat for a steady effort', function () {
    [$hr, $speed] = efStreams(1800, 3.5, 150);

    // 3.5 / 150 * 100 = 2.33
    expect((new EfficiencyFactorAnalyzer)->analyze($hr, $speed))
        ->toBeGreaterThan(2.2)
        ->toBeLessThan(2.4);
});

it('rises when the same pace costs fewer beats', function () {
    $service = new EfficiencyFactorAnalyzer;
    [$hrFit, $speedFit] = efStreams(1800, 3.5, 140);
    [$hrTired, $speedTired] = efStreams(1800, 3.5, 160);

    expect($service->analyze($hrFit, $speedFit))
        ->toBeGreaterThan($service->analyze($hrTired, $speedTired));
});

it('skips a session that is too short', function () {
    [$hr, $speed] = efStreams(600, 3.5, 150);

    expect((new EfficiencyFactorAnalyzer)->analyze($hr, $speed))->toBeNull();
});
