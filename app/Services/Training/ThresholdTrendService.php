<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Enums\Sport;
use App\Models\Activity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ThresholdTrendService
{
    /** Garmin exposes VO2max under a few key spellings across endpoints. */
    private const VO2MAX_KEYS = ['vO2MaxValue', 'vo2MaxValue', 'vo2Max', 'vo2max', 'maxMet'];

    /** Threshold pace is proxied by the best sustained speed near this duration. */
    private const THRESHOLD_DURATION_S = 1800;

    /**
     * A combined view: Garmin's VO2max where it is present, and a threshold
     * pace derived from the athlete's own mean-max efforts, both weekly.
     *
     * @return array{vo2max: list<array{week_start: string, value: float}>, threshold: list<array{week_start: string, speed_mps: float, pace_s_per_km: int}>}
     */
    public function trend(User $user, CarbonImmutable $today, int $weeks): array
    {
        $from = $today->subWeeks($weeks - 1)->startOfWeek();

        $activities = $user->activities()
            ->where('sport', Sport::Run)
            ->where('started_at', '>=', $from->toDateString())
            ->orderBy('started_at')
            ->get(['started_at', 'mean_max', 'raw_summary']);

        return [
            'vo2max' => $this->vo2maxSeries($activities),
            'threshold' => $this->thresholdSeries($activities),
        ];
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @return list<array{week_start: string, value: float}>
     */
    private function vo2maxSeries($activities): array
    {
        $weekly = [];
        foreach ($activities as $activity) {
            $value = $this->vo2maxFrom($activity->raw_summary);
            if ($value === null) {
                continue;
            }
            $week = CarbonImmutable::parse($activity->started_at->toDateString())->startOfWeek()->toDateString();
            $weekly[$week] = max($weekly[$week] ?? 0.0, $value);
        }

        $series = [];
        foreach ($weekly as $week => $value) {
            $series[] = ['week_start' => $week, 'value' => round($value, 1)];
        }

        return $series;
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @return list<array{week_start: string, speed_mps: float, pace_s_per_km: int}>
     */
    private function thresholdSeries($activities): array
    {
        /** @var array<string, list<float>> $weekly */
        $weekly = [];
        foreach ($activities as $activity) {
            $speed = $this->thresholdSpeedFrom($activity->mean_max);
            if ($speed === null) {
                continue;
            }
            $week = CarbonImmutable::parse($activity->started_at->toDateString())->startOfWeek()->toDateString();
            $weekly[$week][] = $speed;
        }

        $series = [];
        foreach ($weekly as $week => $speeds) {
            $avg = array_sum($speeds) / count($speeds);
            $series[] = [
                'week_start' => $week,
                'speed_mps' => round($avg, 2),
                'pace_s_per_km' => $avg > 0.0 ? (int) round(1000 / $avg) : 0,
            ];
        }

        return $series;
    }

    /**
     * @param  array<string, mixed>|null  $raw
     */
    private function vo2maxFrom(?array $raw): ?float
    {
        foreach (self::VO2MAX_KEYS as $key) {
            $value = $raw[$key] ?? null;
            if (is_numeric($value) && (float) $value > 0.0) {
                return (float) $value;
            }
        }

        return null;
    }

    /**
     * Best sustained speed at the duration closest to the threshold anchor.
     *
     * @param  array<int|string, float|int>|null  $meanMax  duration_s => m/s
     */
    private function thresholdSpeedFrom(?array $meanMax): ?float
    {
        if (empty($meanMax)) {
            return null;
        }

        $best = null;
        $bestGap = INF;
        foreach ($meanMax as $duration => $speed) {
            $gap = abs((int) $duration - self::THRESHOLD_DURATION_S);
            if ($gap < $bestGap) {
                $bestGap = $gap;
                $best = (float) $speed;
            }
        }

        return $best !== null && $best > 0.0 ? $best : null;
    }
}
