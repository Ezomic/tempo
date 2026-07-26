<?php

declare(strict_types=1);

namespace App\Services\Training;

class CardiacCostAnalyzer
{
    private const MAX_GAP_S = 10;

    private const MIN_DISTANCE_M = 1000;

    /**
     * Cardiac cost (heartbeats per kilometre) and within-session HR drift
     * (percentage rise from the first half to the second), or null when HR
     * and distance streams are insufficient.
     *
     * @param  array<int, int>  $hrSamples  bpm keyed by unix timestamp
     * @param  array<int, float>  $speedSamples  m/s keyed by unix timestamp
     * @return array{cardiac_cost: float, hr_drift: float}|null
     */
    public function analyze(array $hrSamples, array $speedSamples): ?array
    {
        $paired = [];
        foreach ($speedSamples as $ts => $speed) {
            $hr = $hrSamples[$ts] ?? null;
            if ($hr !== null && $hr > 0 && $speed >= 0.0) {
                $paired[(int) $ts] = ['speed' => (float) $speed, 'hr' => (int) $hr];
            }
        }

        ksort($paired);
        $timestamps = array_keys($paired);
        $count = count($timestamps);
        if ($count < 2) {
            return null;
        }

        $beats = 0.0;
        $distance = 0.0;
        $previous = null;
        foreach ($paired as $ts => $point) {
            if ($previous !== null) {
                $dt = $ts - $previous['ts'];
                if ($dt > 0 && $dt <= self::MAX_GAP_S) {
                    $beats += ($previous['hr'] / 60.0) * $dt;
                    $distance += $previous['speed'] * $dt;
                }
            }
            $previous = ['ts' => $ts, 'hr' => $point['hr'], 'speed' => $point['speed']];
        }

        if ($distance < self::MIN_DISTANCE_M) {
            return null;
        }

        return [
            'cardiac_cost' => round($beats / ($distance / 1000), 1),
            'hr_drift' => $this->hrDrift($paired, $timestamps),
        ];
    }

    /**
     * @param  array<int, array{speed: float, hr: int}>  $paired
     * @param  list<int>  $timestamps
     */
    private function hrDrift(array $paired, array $timestamps): float
    {
        $count = count($timestamps);
        $mid = $timestamps[0] + intdiv($timestamps[$count - 1] - $timestamps[0], 2);

        $firstSum = 0;
        $firstN = 0;
        $secondSum = 0;
        $secondN = 0;
        foreach ($paired as $ts => $point) {
            if ($ts <= $mid) {
                $firstSum += $point['hr'];
                $firstN++;
            } else {
                $secondSum += $point['hr'];
                $secondN++;
            }
        }

        if ($firstN === 0 || $secondN === 0) {
            return 0.0;
        }

        $firstAvg = $firstSum / $firstN;
        $secondAvg = $secondSum / $secondN;

        return $firstAvg > 0 ? round((($secondAvg - $firstAvg) / $firstAvg) * 100, 1) : 0.0;
    }
}
