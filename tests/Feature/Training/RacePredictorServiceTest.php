<?php

declare(strict_types=1);

use App\Services\Training\RacePredictorService;

// A steady 4.0 m/s runner: 3600s at 4 m/s covers 14.4 km.
function meanMaxCurve(float $speed = 4.0): array
{
    return [60 => $speed, 300 => $speed, 600 => $speed, 1200 => $speed, 1800 => $speed, 3600 => $speed];
}

it('predicts all configured distances slower as they lengthen', function () {
    $predictions = (new RacePredictorService)->predict(meanMaxCurve(), 40.0);

    $labels = array_column($predictions, 'label');
    expect($labels)->toBe(['5K', '10K', 'Half', 'Marathon']);

    $seconds = array_column($predictions, 'seconds');
    // Longer distance, longer time.
    expect($seconds[0])->toBeLessThan($seconds[1])
        ->and($seconds[1])->toBeLessThan($seconds[2])
        ->and($seconds[2])->toBeLessThan($seconds[3]);
});

it('predicts a sane 5K for a 4 m/s runner', function () {
    $predictions = (new RacePredictorService)->predict(meanMaxCurve(4.0), 40.0);
    $fiveK = collect($predictions)->firstWhere('distance_m', 5000);

    // 5000 m at ~4 m/s is ~1250 s; Riegel from a shorter anchor keeps it close.
    expect($fiveK['seconds'])->toBeGreaterThan(1100)
        ->and($fiveK['seconds'])->toBeLessThan(1400);
});

it('returns nothing without a mean-max curve', function () {
    expect((new RacePredictorService)->predict([], 40.0))->toBe([]);
});

it('predicts faster when projected fitness is higher', function () {
    $service = new RacePredictorService;
    $base = $service->predict(meanMaxCurve(), 40.0, 40.0);
    $fitter = $service->predict(meanMaxCurve(), 40.0, 55.0);
    $detrained = $service->predict(meanMaxCurve(), 40.0, 30.0);

    expect($fitter[3]['seconds'])->toBeLessThan($base[3]['seconds'])
        ->and($detrained[3]['seconds'])->toBeGreaterThan($base[3]['seconds']);
});
