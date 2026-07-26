<?php

declare(strict_types=1);

use App\Services\Training\IntervalAnalyzer;

/**
 * @param  list<float>  $speeds
 * @return list<array{duration_s: int, distance_m: float, avg_speed_mps: float, avg_hr: int|null}>
 */
function lapsFromSpeeds(array $speeds): array
{
    return array_map(fn (float $speed): array => [
        'duration_s' => 120,
        'distance_m' => round($speed * 120, 1),
        'avg_speed_mps' => $speed,
        'avg_hr' => 160,
    ], $speeds);
}

it('detects a structured session and scores each work rep', function () {
    // warmup, work, recovery, work, recovery, faded work, cooldown
    $laps = lapsFromSpeeds([3.0, 5.0, 2.5, 5.0, 2.5, 4.6, 3.0]);

    $analysis = (new IntervalAnalyzer)->analyze($laps);

    expect($analysis['structured'])->toBeTrue()
        ->and($analysis['counts']['work'])->toBe(3)
        ->and($analysis['counts']['hit'])->toBe(2)
        ->and($analysis['counts']['slightly_off'])->toBe(1)
        ->and($analysis['counts']['missed'])->toBe(0);
});

it('treats an even-paced run as unstructured', function () {
    $laps = lapsFromSpeeds([3.0, 3.02, 2.98, 3.01, 3.0]);

    $analysis = (new IntervalAnalyzer)->analyze($laps);

    expect($analysis['structured'])->toBeFalse()
        ->and($analysis['target_speed_mps'])->toBeNull()
        ->and($analysis['counts']['work'])->toBe(0)
        // Laps are still returned, just without a verdict.
        ->and($analysis['intervals'])->toHaveCount(5)
        ->and($analysis['intervals'][0]['verdict'])->toBeNull();
});

it('needs at least three laps to be structured', function () {
    $analysis = (new IntervalAnalyzer)->analyze(lapsFromSpeeds([3.0, 5.0]));

    expect($analysis['structured'])->toBeFalse();
});

it('marks a slow work rep as missed', function () {
    // Two strong reps set the target; the third fades well below it.
    $laps = lapsFromSpeeds([3.0, 5.0, 2.5, 5.0, 2.5, 4.0]);

    $analysis = (new IntervalAnalyzer)->analyze($laps);

    $work = array_values(array_filter(
        $analysis['intervals'],
        fn (array $i): bool => $i['type'] === 'work',
    ));

    expect($work[2]['verdict'])->toBe('missed');
});
