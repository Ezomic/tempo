<?php

declare(strict_types=1);

use App\Services\Routing\GpxParser;

const SAMPLE_GPX = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1">
  <trk><trkseg>
    <trkpt lat="52.0000" lon="4.0000"><ele>0</ele></trkpt>
    <trkpt lat="52.0010" lon="4.0000"><ele>10</ele></trkpt>
    <trkpt lat="52.0020" lon="4.0000"><ele>5</ele></trkpt>
  </trkseg></trk>
</gpx>
XML;

it('parses a GPX track into a cumulative profile', function () {
    $profile = (new GpxParser)->parse(SAMPLE_GPX);

    expect($profile)->toHaveCount(3)
        ->and($profile[0]['dist'])->toBe(0.0)
        ->and($profile[2]['dist'])->toBeGreaterThan(200.0) // ~222 m over 0.002 deg lat
        ->and($profile[1]['ele'])->toBe(10.0);
});

it('rejects a track with too few points', function () {
    $xml = '<gpx><trk><trkseg><trkpt lat="52" lon="4"><ele>0</ele></trkpt></trkseg></trk></gpx>';

    expect(fn () => (new GpxParser)->parse($xml))
        ->toThrow(RuntimeException::class);
});
