<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Models\HrZoneSettings;
use App\Models\User;
use App\Support\Payload;
use Carbon\CarbonImmutable;

class ZoneCalibrationService
{
    /** Lower bound of each zone as a fraction of LTHR (Friel run zones). */
    private const ZONE_FRACTIONS = [0.85, 0.90, 0.95, 1.00];

    /**
     * Propose recalibrated zones when the LTHR implied by recent hard efforts
     * differs meaningfully from the stored setting. Never writes; returns null
     * when there is nothing worth changing.
     *
     * @return array{estimated_lthr: int, current_lthr: int|null, proposed_boundaries: list<int>, current_boundaries: list<int>|null, delta: int}|null
     */
    public function suggestion(User $user, CarbonImmutable $today): ?array
    {
        $estimated = $this->estimateLthr($user, $today);
        if ($estimated === null) {
            return null;
        }

        $settings = $user->hrZoneSettings;
        $currentLthr = $settings?->lthr;
        $delta = $currentLthr === null ? $estimated : $estimated - $currentLthr;

        if ($currentLthr !== null && abs($delta) < (int) config('training.zones.calibration_min_delta_bpm')) {
            return null; // stored zones already match recent efforts
        }

        return [
            'estimated_lthr' => $estimated,
            'current_lthr' => $currentLthr,
            'proposed_boundaries' => $this->boundaries($estimated),
            'current_boundaries' => $this->currentBoundaries($settings),
            'delta' => $delta,
        ];
    }

    /**
     * Apply the proposed calibration to the athlete's settings.
     */
    public function apply(User $user, CarbonImmutable $today): bool
    {
        $suggestion = $this->suggestion($user, $today);
        if ($suggestion === null) {
            return false;
        }

        $settings = $user->hrZoneSettings()->firstOrNew([]);
        $settings->forceFill([
            'lthr' => $suggestion['estimated_lthr'],
            'zone_boundaries' => $suggestion['proposed_boundaries'],
        ])->save();

        return true;
    }

    /**
     * Best average HR sustained over a threshold-length effort approximates
     * LTHR. Uses the hardest sustained (20 to 60 min) session in the window.
     */
    private function estimateLthr(User $user, CarbonImmutable $today): ?int
    {
        $lookback = (int) config('training.zones.calibration_lookback_days');

        $avgHr = $user->activities()
            ->whereNotNull('avg_hr')
            ->whereBetween('duration_s', [1200, 3600])
            ->where('started_at', '>=', $today->subDays($lookback)->toDateString())
            ->max('avg_hr');

        return $avgHr === null ? null : Payload::toInt($avgHr);
    }

    /**
     * @return list<int>
     */
    private function boundaries(int $lthr): array
    {
        return array_map(fn (float $fraction): int => (int) round($lthr * $fraction), self::ZONE_FRACTIONS);
    }

    /**
     * @return list<int>|null
     */
    private function currentBoundaries(?HrZoneSettings $settings): ?array
    {
        $boundaries = $settings?->zone_boundaries;

        return is_array($boundaries) && $boundaries !== []
            ? array_values(array_map(intval(...), $boundaries))
            : null;
    }
}
