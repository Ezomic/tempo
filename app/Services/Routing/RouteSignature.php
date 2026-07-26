<?php

declare(strict_types=1);

namespace App\Services\Routing;

class RouteSignature
{
    /** Coordinate rounding: 3 decimals is roughly a 110 m grid. */
    private const COORD_PRECISION = 3;

    /** Distance bucket so the same loop run slightly long/short still matches. */
    private const DISTANCE_BUCKET_M = 500;

    /**
     * A stable key for a route from its start/finish and rounded distance, so
     * repeats of the same loop group together. Null when there is no usable
     * GPS track.
     *
     * @param  array<int, array{0: float, 1: float}>  $positions  [lat, lng] keyed by timestamp
     */
    public function forPositions(array $positions, ?float $distanceM): ?string
    {
        if (count($positions) < 2) {
            return null;
        }

        ksort($positions);
        $start = reset($positions);
        $end = end($positions);

        $startKey = $this->coord($start[0]).','.$this->coord($start[1]);
        $endKey = $this->coord($end[0]).','.$this->coord($end[1]);
        $bucket = $distanceM === null
            ? 'na'
            : (string) ((int) round($distanceM / self::DISTANCE_BUCKET_M));

        return $startKey.'|'.$endKey.'|'.$bucket;
    }

    private function coord(float $value): string
    {
        return number_format($value, self::COORD_PRECISION, '.', '');
    }
}
