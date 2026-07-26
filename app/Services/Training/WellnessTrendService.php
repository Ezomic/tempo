<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Models\User;
use App\Models\WellnessDay;
use Carbon\CarbonImmutable;

class WellnessTrendService
{
    private const BASELINE_WINDOW = 7;

    /**
     * Daily sleep, HRV, and resting HR over the window, each with a trailing
     * rolling baseline. Missing days are kept as gaps (null), not zeroed.
     *
     * @return list<array{date: string, sleep_hours: float|null, hrv: int|null, resting_hr: int|null, baseline_hrv: float|null, baseline_resting_hr: float|null, baseline_sleep: float|null}>
     */
    public function trend(User $user, CarbonImmutable $today, int $days): array
    {
        $from = $today->subDays($days - 1)->startOfDay();

        $byDate = $user->wellnessDays()
            ->whereBetween('date', [$from->toDateString().' 00:00:00', $today->toDateString().' 23:59:59'])
            ->orderBy('date')
            ->get()
            ->keyBy(fn (WellnessDay $day): string => $day->date->toDateString());

        $hrvHistory = [];
        $rhrHistory = [];
        $sleepHistory = [];

        $points = [];
        for ($cursor = $from; $cursor->lessThanOrEqualTo($today); $cursor = $cursor->addDay()) {
            $key = $cursor->toDateString();
            $day = $byDate->get($key);

            $sleepHours = $day?->sleep_duration_s !== null ? round($day->sleep_duration_s / 3600, 2) : null;
            $hrv = $day?->hrv_last_night_ms;
            $rhr = $day?->resting_hr;

            $this->push($hrvHistory, $hrv);
            $this->push($rhrHistory, $rhr);
            $this->push($sleepHistory, $sleepHours);

            $points[] = [
                'date' => $key,
                'sleep_hours' => $sleepHours,
                'hrv' => $hrv,
                'resting_hr' => $rhr,
                'baseline_hrv' => $this->baseline($hrvHistory),
                'baseline_resting_hr' => $this->baseline($rhrHistory),
                'baseline_sleep' => $this->baseline($sleepHistory),
            ];
        }

        return $points;
    }

    /**
     * @param  list<float>  $history
     */
    private function push(array &$history, float|int|null $value): void
    {
        if ($value !== null) {
            $history[] = (float) $value;
            if (count($history) > self::BASELINE_WINDOW) {
                array_shift($history);
            }
        }
    }

    /**
     * @param  list<float>  $history
     */
    private function baseline(array $history): ?float
    {
        return $history === [] ? null : round(array_sum($history) / count($history), 1);
    }
}
