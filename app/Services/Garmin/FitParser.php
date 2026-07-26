<?php

declare(strict_types=1);

namespace App\Services\Garmin;

use adriangibbons\phpFITFileAnalysis;
use App\DataObjects\ParsedActivity;

class FitParser
{
    public function parseFile(string $path): ParsedActivity
    {
        return $this->extract(new phpFITFileAnalysis($path));
    }

    public function parseData(string $bytes): ParsedActivity
    {
        return $this->extract(new phpFITFileAnalysis($bytes, ['input_is_data' => true]));
    }

    private function extract(phpFITFileAnalysis $fit): ParsedActivity
    {
        $record = is_array($fit->data_mesgs['record'] ?? null) ? $fit->data_mesgs['record'] : [];

        return new ParsedActivity(
            hrSamples: $this->intStream($record['heart_rate'] ?? []),
            speedSamples: $this->floatStream($record['speed'] ?? []),
            positions: $this->positionStream(
                $record['position_lat'] ?? [],
                $record['position_long'] ?? [],
            ),
            laps: $this->laps(is_array($fit->data_mesgs['lap'] ?? null) ? $fit->data_mesgs['lap'] : []),
        );
    }

    /**
     * @param  array<string, mixed>  $lap
     * @return list<array{duration_s: int, distance_m: float, avg_speed_mps: float, avg_hr: int|null}>
     */
    private function laps(array $lap): array
    {
        $durations = array_values($this->asArray($lap['total_timer_time'] ?? $lap['total_elapsed_time'] ?? []));
        $distances = array_values($this->asArray($lap['total_distance'] ?? []));
        $speeds = array_values($this->asArray($lap['avg_speed'] ?? []));
        $heartRates = array_values($this->asArray($lap['avg_heart_rate'] ?? []));

        $laps = [];
        foreach ($durations as $i => $duration) {
            if (! is_numeric($duration) || (int) $duration <= 0) {
                continue;
            }

            $seconds = (int) $duration;
            $distance = is_numeric($distances[$i] ?? null) ? (float) $distances[$i] : 0.0;
            $speed = is_numeric($speeds[$i] ?? null)
                ? (float) $speeds[$i]
                : $distance / $seconds;
            $hr = is_numeric($heartRates[$i] ?? null) ? (int) $heartRates[$i] : null;

            $laps[] = [
                'duration_s' => $seconds,
                'distance_m' => round($distance, 1),
                'avg_speed_mps' => round($speed, 3),
                'avg_hr' => $hr,
            ];
        }

        return $laps;
    }

    /**
     * @return array<int, int>
     */
    private function intStream(mixed $raw): array
    {
        $out = [];
        foreach ($this->asArray($raw) as $timestamp => $value) {
            if (is_numeric($timestamp) && is_numeric($value) && (int) $value > 0) {
                $out[(int) $timestamp] = (int) $value;
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * @return array<int, float>
     */
    private function floatStream(mixed $raw): array
    {
        $out = [];
        foreach ($this->asArray($raw) as $timestamp => $value) {
            if (is_numeric($timestamp) && is_numeric($value)) {
                $out[(int) $timestamp] = (float) $value;
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * @return array<int, array{0: float, 1: float}>
     */
    private function positionStream(mixed $lat, mixed $lng): array
    {
        $lats = $this->floatStream($lat);
        $lngs = $this->floatStream($lng);

        $out = [];
        foreach ($lats as $timestamp => $latitude) {
            if (isset($lngs[$timestamp])) {
                $out[$timestamp] = [$latitude, $lngs[$timestamp]];
            }
        }

        return $out;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function asArray(mixed $raw): array
    {
        return is_array($raw) ? $raw : [];
    }
}
