<?php

declare(strict_types=1);

namespace App\Services\Training;

class EfficiencyFactorAnalyzer
{
    /**
     * Efficiency factor: mean speed divided by mean heart rate over a
     * steady aerobic effort, scaled for readability. An effort-independent
     * proxy for aerobic fitness. Null when too short, too variable, or
     * missing HR.
     *
     * @param  array<int, int>  $hrSamples  bpm keyed by unix timestamp
     * @param  array<int, float>  $speedSamples  m/s keyed by unix timestamp
     */
    public function analyze(array $hrSamples, array $speedSamples): ?float
    {
        $speeds = [];
        $hrs = [];
        foreach ($speedSamples as $ts => $speed) {
            $hr = $hrSamples[$ts] ?? null;
            if ($hr !== null && $hr > 0 && $speed > 0.0) {
                $speeds[(int) $ts] = (float) $speed;
                $hrs[(int) $ts] = (int) $hr;
            }
        }

        $timestamps = array_keys($speeds);
        $count = count($timestamps);
        if ($count < 2) {
            return null;
        }

        sort($timestamps);
        $duration = $timestamps[$count - 1] - $timestamps[0];
        if ($duration < (int) config('training.efficiency.min_seconds')) {
            return null;
        }

        if ($this->coefficientOfVariation(array_values($speeds)) > (float) config('training.efficiency.max_speed_cov')) {
            return null;
        }

        $meanSpeed = array_sum($speeds) / $count;
        $meanHr = array_sum($hrs) / $count;

        return round(($meanSpeed / $meanHr) * 100, 2);
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
