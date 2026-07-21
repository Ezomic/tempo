<?php

declare(strict_types=1);

namespace App\Services\Garmin;

use App\DataObjects\ParsedActivity;

/**
 * Downsamples a parsed activity's per-second streams to a compact, chart-ready
 * shape that is precomputed on sync and stored on disk, so activity detail
 * views never re-parse a FIT file.
 */
class StreamBuilder
{
    private const MAX_POINTS = 300;

    /**
     * @return array{
     *     t: list<int>,
     *     hr: list<int|null>,
     *     speed: list<float|null>,
     *     lat: list<float>|null,
     *     lng: list<float>|null,
     * }
     */
    public function build(ParsedActivity $parsed): array
    {
        $timestamps = array_keys($parsed->hrSamples + $parsed->speedSamples);
        sort($timestamps);
        $timestamps = $this->downsample($timestamps);

        $start = $timestamps[0] ?? 0;

        $t = [];
        $hr = [];
        $speed = [];
        foreach ($timestamps as $timestamp) {
            $t[] = $timestamp - $start;
            $hr[] = $parsed->hrSamples[$timestamp] ?? null;
            $speed[] = $parsed->speedSamples[$timestamp] ?? null;
        }

        [$lat, $lng] = $this->positionSeries($parsed);

        return [
            't' => $t,
            'hr' => $hr,
            'speed' => $speed,
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    /**
     * @return array{0: list<float>|null, 1: list<float>|null}
     */
    private function positionSeries(ParsedActivity $parsed): array
    {
        if ($parsed->positions === []) {
            return [null, null];
        }

        $keys = array_keys($parsed->positions);
        sort($keys);
        $keys = $this->downsample($keys);

        $lat = [];
        $lng = [];
        foreach ($keys as $key) {
            $lat[] = $parsed->positions[$key][0];
            $lng[] = $parsed->positions[$key][1];
        }

        return [$lat, $lng];
    }

    /**
     * @param  list<int>  $values
     * @return list<int>
     */
    private function downsample(array $values): array
    {
        $count = count($values);
        if ($count <= self::MAX_POINTS) {
            return $values;
        }

        $stride = (int) ceil($count / self::MAX_POINTS);
        $out = [];
        for ($i = 0; $i < $count; $i += $stride) {
            $out[] = $values[$i];
        }

        // Always keep the final point so the series ends where the activity did.
        if (end($out) !== $values[$count - 1]) {
            $out[] = $values[$count - 1];
        }

        return $out;
    }
}
