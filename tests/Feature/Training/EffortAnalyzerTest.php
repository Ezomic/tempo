<?php

declare(strict_types=1);

use App\Services\Training\EffortAnalyzer;

/**
 * @return array<int, float>
 */
function constantSpeedStream(float $mps, int $seconds): array
{
    $samples = [];
    for ($t = 0; $t <= $seconds; $t++) {
        $samples[1_700_000_000 + $t] = $mps;
    }

    return $samples;
}

it('finds the fastest time for each reachable distance', function () {
    // 5 m/s for 600s covers 3000m, so only the 1K best exists (200s).
    $stream = constantSpeedStream(5.0, 600);

    $efforts = (new EffortAnalyzer)->bestEfforts($stream);

    expect($efforts[1000])->toBe(200)
        ->and($efforts)->not->toHaveKey(5000)
        ->and($efforts)->not->toHaveKey(10000);
});

it('computes mean-max speed only for reachable durations', function () {
    $stream = constantSpeedStream(5.0, 600);

    $meanMax = (new EffortAnalyzer)->meanMax($stream);

    expect($meanMax[60])->toBe(5.0)
        ->and($meanMax[300])->toBe(5.0)
        ->and($meanMax[600])->toBe(5.0)
        ->and($meanMax)->not->toHaveKey(1200);
});

it('ignores long pauses when accumulating distance', function () {
    // Two 100s blocks at 4 m/s with a 1-hour gap between them.
    $samples = [];
    for ($t = 0; $t <= 100; $t++) {
        $samples[1_700_000_000 + $t] = 4.0;
    }
    for ($t = 0; $t <= 100; $t++) {
        $samples[1_700_003_700 + $t] = 4.0;
    }

    $meanMax = (new EffortAnalyzer)->meanMax($samples);

    // Each block is 400m/100s = 4 m/s; the gap must not inflate anything.
    expect($meanMax[60])->toBe(4.0);
});
