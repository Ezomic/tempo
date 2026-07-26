<?php

declare(strict_types=1);

namespace App\Services\Training;

class AerobicDecouplingAnalyzer
{
    /**
     * Aerobic decoupling (Pa:Hr drift) for a steady session, or null when the
     * session is too short, too variable (intervals), or missing data.
     *
     * The pace-to-HR efficiency ratio is measured for each half of the effort;
     * the percentage the ratio falls from the first half to the second is the
     * decoupling. Positive means HR drifted up relative to pace.
     *
     * @param  array<int, int>  $hrSamples  bpm keyed by unix timestamp
     * @param  array<int, float>  $speedSamples  m/s keyed by unix timestamp
     */
    public function analyze(array $hrSamples, array $speedSamples): ?float
    {
        $paired = [];
        foreach ($speedSamples as $ts => $speed) {
            $hr = $hrSamples[$ts] ?? null;
            if ($hr !== null && $hr > 0 && $speed > 0.0) {
                $paired[(int) $ts] = ['speed' => (float) $speed, 'hr' => (int) $hr];
            }
        }

        ksort($paired);
        $timestamps = array_keys($paired);
        $count = count($timestamps);

        if ($count < 2) {
            return null;
        }

        $duration = $timestamps[$count - 1] - $timestamps[0];
        if ($duration < (int) config('training.decoupling.min_seconds')) {
            return null;
        }

        $speeds = array_map(fn (array $p): float => $p['speed'], $paired);
        if ($this->coefficientOfVariation($speeds) > (float) config('training.decoupling.max_speed_cov')) {
            return null; // intervals or fartlek, not a steady effort
        }

        $mid = $timestamps[0] + intdiv($duration, 2);
        $firstRatio = $this->efficiency($paired, fn (int $ts): bool => $ts <= $mid);
        $secondRatio = $this->efficiency($paired, fn (int $ts): bool => $ts > $mid);

        if ($firstRatio === null || $secondRatio === null || $firstRatio <= 0.0) {
            return null;
        }

        return round((($firstRatio - $secondRatio) / $firstRatio) * 100, 1);
    }

    /**
     * Mean speed / mean HR over the samples the predicate accepts.
     *
     * @param  array<int, array{speed: float, hr: int}>  $paired
     * @param  callable(int): bool  $accept
     */
    private function efficiency(array $paired, callable $accept): ?float
    {
        $speedSum = 0.0;
        $hrSum = 0;
        $n = 0;
        foreach ($paired as $ts => $point) {
            if (! $accept($ts)) {
                continue;
            }
            $speedSum += $point['speed'];
            $hrSum += $point['hr'];
            $n++;
        }

        if ($n === 0 || $hrSum === 0) {
            return null;
        }

        return ($speedSum / $n) / ($hrSum / $n);
    }

    /**
     * @param  array<int, float>  $values
     */
    private function coefficientOfVariation(array $values): float
    {
        $n = count($values);
        if ($n === 0) {
            return 0.0;
        }

        $mean = array_sum($values) / $n;
        if ($mean <= 0.0) {
            return 0.0;
        }

        $variance = 0.0;
        foreach ($values as $value) {
            $variance += ($value - $mean) ** 2;
        }

        return sqrt($variance / $n) / $mean;
    }
}
