<?php

declare(strict_types=1);

use App\Services\Training\CardiacCostAnalyzer;

/**
 * @return array{0: array<int, int>, 1: array<int, float>}
 */
function cardiacStreams(int $seconds, float $speed, int $startHr, int $endHr): array
{
    $hr = [];
    $spd = [];
    for ($t = 0; $t <= $seconds; $t++) {
        $ts = 1_700_000_000 + $t;
        $hr[$ts] = (int) round($startHr + ($endHr - $startHr) * ($t / $seconds));
        $spd[$ts] = $speed;
    }

    return [$hr, $spd];
}

it('computes cardiac cost as beats per km', function () {
    // 3.0 m/s for 1000 s = 3000 m; steady 150 bpm = 2.5 beats/s.
    [$hr, $speed] = cardiacStreams(1000, 3.0, 150, 150);

    $result = (new CardiacCostAnalyzer)->analyze($hr, $speed);

    // ~2500 beats over 3.0 km -> ~833 beats/km.
    expect($result['cardiac_cost'])->toBeGreaterThan(800.0)
        ->and($result['cardiac_cost'])->toBeLessThan(870.0);
});

it('reports positive HR drift when HR rises through the session', function () {
    [$hr, $speed] = cardiacStreams(1200, 3.0, 140, 160);

    expect((new CardiacCostAnalyzer)->analyze($hr, $speed)['hr_drift'])
        ->toBeGreaterThan(5.0);
});

it('returns null when the distance is too short', function () {
    // 0.5 m/s for 600 s = 300 m, under the 1 km floor.
    [$hr, $speed] = cardiacStreams(600, 0.5, 150, 150);

    expect((new CardiacCostAnalyzer)->analyze($hr, $speed))->toBeNull();
});
