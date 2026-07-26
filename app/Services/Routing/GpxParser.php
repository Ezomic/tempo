<?php

declare(strict_types=1);

namespace App\Services\Routing;

use RuntimeException;

class GpxParser
{
    private const EARTH_RADIUS_M = 6_371_000.0;

    /**
     * Parse a GPX track into a cumulative distance/elevation profile.
     *
     * @return list<array{dist: float, ele: float}>
     */
    public function parse(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_use_internal_errors($previous);

        if ($doc === false) {
            throw new RuntimeException('Could not parse the GPX file.');
        }

        $points = [];
        foreach ($doc->xpath('//*[local-name()="trkpt"]') ?: [] as $node) {
            $lat = isset($node['lat']) ? (float) $node['lat'] : null;
            $lng = isset($node['lon']) ? (float) $node['lon'] : null;
            if ($lat === null || $lng === null) {
                continue;
            }

            $eleNodes = $node->xpath('*[local-name()="ele"]');
            $ele = is_array($eleNodes) && $eleNodes !== [] ? (float) $eleNodes[0] : 0.0;

            $points[] = ['lat' => $lat, 'lng' => $lng, 'ele' => $ele];
        }

        if (count($points) < 2) {
            throw new RuntimeException('The GPX file has too few track points.');
        }

        return $this->toProfile($points);
    }

    /**
     * @param  list<array{lat: float, lng: float, ele: float}>  $points
     * @return list<array{dist: float, ele: float}>
     */
    private function toProfile(array $points): array
    {
        $profile = [['dist' => 0.0, 'ele' => $points[0]['ele']]];
        $cumulative = 0.0;

        for ($i = 1; $i < count($points); $i++) {
            $cumulative += $this->haversine($points[$i - 1], $points[$i]);
            $profile[] = ['dist' => round($cumulative, 2), 'ele' => $points[$i]['ele']];
        }

        return $profile;
    }

    /**
     * @param  array{lat: float, lng: float, ele: float}  $a
     * @param  array{lat: float, lng: float, ele: float}  $b
     */
    private function haversine(array $a, array $b): float
    {
        $lat1 = deg2rad($a['lat']);
        $lat2 = deg2rad($b['lat']);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad($b['lng'] - $a['lng']);

        $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(min(1.0, sqrt($h)));
    }
}
