<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Support\Payload;

class PacingPlanService
{
    /**
     * Distribute a target finish time across splits at even grade-adjusted
     * effort, so uphill splits are slower and downhill splits faster while the
     * total holds. Optional heat/wind relaxes every split's target pace.
     *
     * @param  list<array{dist: float, ele: float}>  $profile  cumulative distance + elevation
     * @return array{total_distance_m: float, target_seconds: int, total_seconds: int, weather_factor: float, splits: list<array{index: int, distance_m: float, avg_grade: float, pace_per_km: int, cumulative_s: int}>}
     */
    public function plan(array $profile, int $targetSeconds, int $splitMeters, ?float $tempC = null, ?float $windKmh = null): array
    {
        $segments = $this->segments($profile);
        $totalDistance = $profile[count($profile) - 1]['dist'];
        $totalGa = array_sum(array_map(fn (array $s): float => $s['ga'], $segments));

        $weatherFactor = $this->weatherFactor($tempC, $windKmh);
        $effortPace = $totalGa > 0.0 ? ($targetSeconds / $totalGa) : 0.0; // s per GA metre

        $splits = [];
        $cumulative = 0.0;
        $index = 1;
        foreach ($this->bucketed($segments, $splitMeters) as $bucket) {
            $time = $bucket['ga'] * $effortPace * $weatherFactor;
            $cumulative += $time;
            $distance = $bucket['distance'];

            $splits[] = [
                'index' => $index++,
                'distance_m' => round($distance, 1),
                'avg_grade' => $distance > 0.0 ? round(($bucket['rise'] / $distance) * 100, 1) : 0.0,
                'pace_per_km' => $distance > 0.0 ? (int) round(($time / $distance) * 1000) : 0,
                'cumulative_s' => (int) round($cumulative),
            ];
        }

        return [
            'total_distance_m' => round($totalDistance, 1),
            'target_seconds' => $targetSeconds,
            'total_seconds' => (int) round($cumulative),
            'weather_factor' => round($weatherFactor, 3),
            'splits' => $splits,
        ];
    }

    /**
     * @param  list<array{dist: float, ele: float}>  $profile
     * @return list<array{distance: float, rise: float, ga: float}>
     */
    private function segments(array $profile): array
    {
        $segments = [];
        for ($i = 1; $i < count($profile); $i++) {
            $distance = $profile[$i]['dist'] - $profile[$i - 1]['dist'];
            if ($distance <= 0.0) {
                continue;
            }

            $rise = $profile[$i]['ele'] - $profile[$i - 1]['ele'];
            $grade = $rise / $distance;

            $segments[] = [
                'distance' => $distance,
                'rise' => $rise,
                'ga' => $distance * $this->gradeFactor($grade),
            ];
        }

        return $segments;
    }

    /**
     * Grade-adjusted cost multiplier from Minetti's energy cost of running,
     * normalised to flat ground.
     */
    private function gradeFactor(float $grade): float
    {
        $grade = max(-0.35, min(0.35, $grade));
        $cost = 155.4 * $grade ** 5 - 30.4 * $grade ** 4 - 43.3 * $grade ** 3
            + 46.3 * $grade ** 2 + 19.5 * $grade + 3.6;

        return max(0.4, $cost / 3.6);
    }

    private function weatherFactor(?float $tempC, ?float $windKmh): float
    {
        $factor = 1.0;
        $heatC = Payload::toFloat(config('training.weather.heat_c'));
        $windLimit = Payload::toFloat(config('training.weather.wind_kmh'));

        if ($tempC !== null && $tempC > $heatC) {
            $factor += ($tempC - $heatC) * 0.005;
        }
        if ($windKmh !== null && $windKmh > $windLimit) {
            $factor += ($windKmh - $windLimit) * 0.002;
        }

        return $factor;
    }

    /**
     * @param  list<array{distance: float, rise: float, ga: float}>  $segments
     * @return list<array{distance: float, rise: float, ga: float}>
     */
    private function bucketed(array $segments, int $splitMeters): array
    {
        $buckets = [];
        $current = ['distance' => 0.0, 'rise' => 0.0, 'ga' => 0.0];

        foreach ($segments as $segment) {
            $current['distance'] += $segment['distance'];
            $current['rise'] += $segment['rise'];
            $current['ga'] += $segment['ga'];

            if ($current['distance'] >= $splitMeters) {
                $buckets[] = $current;
                $current = ['distance' => 0.0, 'rise' => 0.0, 'ga' => 0.0];
            }
        }

        if ($current['distance'] > 0.0) {
            $buckets[] = $current;
        }

        return $buckets;
    }
}
