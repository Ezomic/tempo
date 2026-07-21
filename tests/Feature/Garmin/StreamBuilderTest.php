<?php

declare(strict_types=1);

use App\DataObjects\ParsedActivity;
use App\Services\Garmin\StreamBuilder;

it('builds aligned, time-relative streams', function () {
    $hr = [];
    $speed = [];
    $positions = [];
    for ($i = 0; $i < 5; $i++) {
        $ts = 1_700_000_000 + $i;
        $hr[$ts] = 140 + $i;
        $speed[$ts] = 3.0 + $i;
        $positions[$ts] = [52.0 + $i * 0.001, 4.0 + $i * 0.001];
    }

    $streams = (new StreamBuilder)->build(new ParsedActivity($hr, $speed, $positions));

    expect($streams['t'])->toBe([0, 1, 2, 3, 4])
        ->and($streams['hr'])->toBe([140, 141, 142, 143, 144])
        ->and($streams['speed'][0])->toBe(3.0)
        ->and($streams['lat'])->toHaveCount(5)
        ->and($streams['lng'])->toHaveCount(5);
});

it('downsamples long streams and omits absent GPS', function () {
    $hr = [];
    for ($i = 0; $i < 1000; $i++) {
        $hr[1_700_000_000 + $i] = 150;
    }

    $streams = (new StreamBuilder)->build(new ParsedActivity($hr));

    expect(count($streams['t']))->toBeLessThanOrEqual(301)
        ->and(count($streams['t']))->toBeGreaterThan(100)
        ->and($streams['lat'])->toBeNull()
        ->and($streams['lng'])->toBeNull();
});
