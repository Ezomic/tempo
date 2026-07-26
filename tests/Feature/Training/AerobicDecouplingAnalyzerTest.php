<?php

declare(strict_types=1);

use App\Services\Training\AerobicDecouplingAnalyzer;

/**
 * Build aligned HR/speed streams: constant speed, HR rising by `drift` bpm
 * across the session (or 0 for a flat effort).
 *
 * @return array{0: array<int, int>, 1: array<int, float>}
 */
function driftStreams(int $seconds, float $speed, int $startHr, int $endHr): array
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

it('reports positive decoupling when HR drifts up at steady pace', function () {
    [$hr, $speed] = driftStreams(1800, 3.5, 140, 158);

    $result = (new AerobicDecouplingAnalyzer)->analyze($hr, $speed);

    expect($result)->toBeGreaterThan(5.0);
});

it('reports near-zero decoupling for a flat effort', function () {
    [$hr, $speed] = driftStreams(1800, 3.5, 145, 145);

    expect((new AerobicDecouplingAnalyzer)->analyze($hr, $speed))
        ->toBeGreaterThanOrEqual(-1.0)
        ->toBeLessThanOrEqual(1.0);
});

it('skips a session that is too short', function () {
    [$hr, $speed] = driftStreams(600, 3.5, 140, 150); // 10 min, under the 20 min floor

    expect((new AerobicDecouplingAnalyzer)->analyze($hr, $speed))->toBeNull();
});

it('skips a highly variable interval session', function () {
    $hr = [];
    $speed = [];
    for ($t = 0; $t <= 1800; $t++) {
        $ts = 1_700_000_000 + $t;
        $hr[$ts] = 150;
        // Alternate hard/easy every 200 s: big speed swings.
        $speed[$ts] = intdiv($t, 200) % 2 === 0 ? 5.5 : 2.0;
    }

    expect((new AerobicDecouplingAnalyzer)->analyze($hr, $speed))->toBeNull();
});
