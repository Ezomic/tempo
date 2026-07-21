<?php

declare(strict_types=1);

namespace App\Services\Garmin;

use App\Models\HrZoneSettings;

/**
 * Banister TRIMP from a heart-rate stream, computed identically for running and
 * cycling so cross-sport training load is comparable. Uses heart-rate reserve
 * with the sex-specific exponential weighting.
 */
final class TrimpCalculator
{
    private const MALE_CONSTANT = 1.92;

    private const FEMALE_CONSTANT = 1.67;

    // Gaps longer than this (pauses, dropouts) are capped so they don't inflate load.
    private const MAX_INTERVAL_SECONDS = 30;

    /**
     * @param  array<int, int>  $hrSamples  bpm keyed by unix timestamp
     */
    public function trimp(array $hrSamples, HrZoneSettings $settings): ?float
    {
        if (! $settings->hasHeartRateRange() || count($hrSamples) < 2) {
            return null;
        }

        $maxHr = (int) $settings->max_hr;
        $restingHr = (int) $settings->resting_hr;
        $reserve = $maxHr - $restingHr;

        if ($reserve <= 0) {
            return null;
        }

        $constant = $settings->sex === HrZoneSettings::SEX_FEMALE
            ? self::FEMALE_CONSTANT
            : self::MALE_CONSTANT;

        $timestamps = array_keys($hrSamples);
        $trimp = 0.0;

        for ($i = 1, $n = count($timestamps); $i < $n; $i++) {
            $seconds = min($timestamps[$i] - $timestamps[$i - 1], self::MAX_INTERVAL_SECONDS);
            if ($seconds <= 0) {
                continue;
            }

            $bpm = $hrSamples[$timestamps[$i - 1]];
            $hrr = max(0.0, min(1.0, ($bpm - $restingHr) / $reserve));

            $trimp += ($seconds / 60) * $hrr * 0.64 * exp($constant * $hrr);
        }

        return round($trimp, 2);
    }

    /**
     * Seconds spent in each HR zone (1-indexed), using explicit boundaries when
     * set or percentage-of-max defaults otherwise.
     *
     * @param  array<int, int>  $hrSamples
     * @return array<int, int>
     */
    public function zoneSeconds(array $hrSamples, HrZoneSettings $settings): array
    {
        if (count($hrSamples) < 2) {
            return [];
        }

        $boundaries = $this->boundaries($settings);
        $zones = array_fill(1, count($boundaries) + 1, 0);

        $timestamps = array_keys($hrSamples);
        for ($i = 1, $n = count($timestamps); $i < $n; $i++) {
            $seconds = min($timestamps[$i] - $timestamps[$i - 1], self::MAX_INTERVAL_SECONDS);
            if ($seconds <= 0) {
                continue;
            }

            $bpm = $hrSamples[$timestamps[$i - 1]];
            $zone = 1;
            foreach ($boundaries as $threshold) {
                if ($bpm >= $threshold) {
                    $zone++;
                }
            }

            $zones[$zone] += $seconds;
        }

        return $zones;
    }

    /**
     * @return array<int, int> ascending bpm thresholds between zones
     */
    private function boundaries(HrZoneSettings $settings): array
    {
        $explicit = $settings->zone_boundaries;
        if (is_array($explicit) && $explicit !== []) {
            $thresholds = array_map('intval', $explicit);
            sort($thresholds);

            return $thresholds;
        }

        if ($settings->max_hr === null) {
            return [];
        }

        $maxHr = (int) $settings->max_hr;

        return array_map(
            static fn (float $pct): int => (int) round($maxHr * $pct),
            [0.60, 0.70, 0.80, 0.90],
        );
    }
}
