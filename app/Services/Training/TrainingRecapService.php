<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Models\User;
use App\Support\Payload;
use Carbon\CarbonImmutable;

class TrainingRecapService
{
    /**
     * Aggregate totals, PR count, and fitness change over a date range.
     *
     * @return array{totals: array{distance_m: float, elevation_m: float, hours: float, activities: int}, by_sport: array<string, array{distance_m: float, hours: float, activities: int}>, prs: int, ctl_delta: float, ctl_start: float, ctl_end: float}
     */
    public function recap(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $activities = $user->activities()
            ->whereBetween('started_at', [$from->startOfDay(), $to->endOfDay()])
            ->get(['sport', 'distance_m', 'elevation_gain_m', 'moving_time_s', 'duration_s']);

        $totals = ['distance_m' => 0.0, 'elevation_m' => 0.0, 'seconds' => 0, 'activities' => 0];
        $bySport = [];

        foreach ($activities as $activity) {
            $seconds = $activity->moving_time_s ?? $activity->duration_s ?? 0;
            $distance = (float) ($activity->distance_m ?? 0);
            $sport = $activity->sport->value;

            $totals['distance_m'] += $distance;
            $totals['elevation_m'] += (float) ($activity->elevation_gain_m ?? 0);
            $totals['seconds'] += $seconds;
            $totals['activities']++;

            $bySport[$sport] ??= ['distance_m' => 0.0, 'seconds' => 0, 'activities' => 0];
            $bySport[$sport]['distance_m'] += $distance;
            $bySport[$sport]['seconds'] += $seconds;
            $bySport[$sport]['activities']++;
        }

        $prs = $user->personalRecords()
            ->whereBetween('achieved_on', [$from->toDateString(), $to->toDateString()])
            ->count();

        $ctlStart = $this->ctlOn($user, $from);
        $ctlEnd = $this->ctlOn($user, $to);

        return [
            'totals' => [
                'distance_m' => round($totals['distance_m'], 1),
                'elevation_m' => round($totals['elevation_m'], 1),
                'hours' => round($totals['seconds'] / 3600, 1),
                'activities' => $totals['activities'],
            ],
            'by_sport' => $this->formatBySport($bySport),
            'prs' => $prs,
            'ctl_delta' => round($ctlEnd - $ctlStart, 1),
            'ctl_start' => $ctlStart,
            'ctl_end' => $ctlEnd,
        ];
    }

    /**
     * @param  array<string, array{distance_m: float, seconds: int, activities: int}>  $bySport
     * @return array<string, array{distance_m: float, hours: float, activities: int}>
     */
    private function formatBySport(array $bySport): array
    {
        $formatted = [];
        foreach ($bySport as $sport => $data) {
            $formatted[$sport] = [
                'distance_m' => round($data['distance_m'], 1),
                'hours' => round($data['seconds'] / 3600, 1),
                'activities' => $data['activities'],
            ];
        }

        return $formatted;
    }

    private function ctlOn(User $user, CarbonImmutable $date): float
    {
        $ctl = $user->dailyLoadMetrics()
            ->whereDate('date', '<=', $date->toDateString())
            ->orderByDesc('date')
            ->value('ctl');

        return $ctl === null ? 0.0 : round(Payload::toFloat($ctl), 1);
    }
}
