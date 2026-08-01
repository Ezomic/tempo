<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Models\DailyLoadMetric;
use App\Models\User;
use App\Support\Payload;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class FitnessCurveService
{
    private const CTL_DAYS = 42;

    private const ATL_DAYS = 7;

    /**
     * Rebuild the stored CTL/ATL/TSB series for a user from their first
     * activity up to today. Idempotent: it replaces the whole series so a
     * backfill or a late-arriving activity always recomputes cleanly.
     */
    public function recompute(User $user, CarbonImmutable $today): void
    {
        $first = $this->firstActivityDate($user);

        if ($first === null) {
            $user->dailyLoadMetrics()->delete();

            return;
        }

        $daily = $this->dailyTrimp($user, $today);
        $ctlLambda = $this->lambda(self::CTL_DAYS);
        $atlLambda = $this->lambda(self::ATL_DAYS);

        $ctl = 0.0;
        $atl = 0.0;
        $rows = [];
        $cursor = $first;
        $end = $today->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->toDateString();
            $load = $daily[$key] ?? 0.0;
            $prevCtl = $ctl;
            $prevAtl = $atl;
            $ctl = $prevCtl + $ctlLambda * ($load - $prevCtl);
            $atl = $prevAtl + $atlLambda * ($load - $prevAtl);

            $rows[] = [
                'user_id' => $user->id,
                'date' => $key,
                'trimp' => round($load, 1),
                'ctl' => round($ctl, 1),
                'atl' => round($atl, 1),
                'tsb' => round($prevCtl - $prevAtl, 1),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $cursor = $cursor->addDay();
        }

        $user->dailyLoadMetrics()->delete();

        foreach (array_chunk($rows, 500) as $chunk) {
            DailyLoadMetric::insert($chunk);
        }
    }

    /**
     * Stored CTL/ATL/TSB points between two dates, oldest first.
     *
     * @return list<array{date: string, ctl: float, atl: float, tsb: float}>
     */
    public function series(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return array_values($user->dailyLoadMetrics()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get()
            ->map(fn (DailyLoadMetric $m): array => [
                'date' => $m->date->toDateString(),
                'ctl' => round($m->ctl, 1),
                'atl' => round($m->atl, 1),
                'tsb' => round($m->tsb, 1),
            ])
            ->all());
    }

    /**
     * Project the curve forward across planned sessions, seeded from the last
     * stored day. Planned load is estimated from session duration and type.
     *
     * @return list<array{date: string, ctl: float, atl: float, tsb: float}>
     */
    public function project(User $user, CarbonImmutable $today, int $days): array
    {
        $ctl = Payload::toFloat($user->dailyLoadMetrics()->orderByDesc('date')->value('ctl'));
        $atl = Payload::toFloat($user->dailyLoadMetrics()->orderByDesc('date')->value('atl'));

        $planned = $this->plannedLoad($user, $today->addDay(), $today->addDays($days));
        $ctlLambda = $this->lambda(self::CTL_DAYS);
        $atlLambda = $this->lambda(self::ATL_DAYS);

        $points = [];
        $cursor = $today->addDay()->startOfDay();
        $end = $today->addDays($days)->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->toDateString();
            $load = $planned[$key] ?? 0.0;
            $prevCtl = $ctl;
            $prevAtl = $atl;
            $ctl = $prevCtl + $ctlLambda * ($load - $prevCtl);
            $atl = $prevAtl + $atlLambda * ($load - $prevAtl);

            $points[] = [
                'date' => $key,
                'ctl' => round($ctl, 1),
                'atl' => round($atl, 1),
                'tsb' => round($prevCtl - $prevAtl, 1),
            ];

            $cursor = $cursor->addDay();
        }

        return $points;
    }

    /**
     * @return array<string, float> date => summed TRIMP
     */
    private function dailyTrimp(User $user, CarbonImmutable $today): array
    {
        $activities = $user->activities()
            ->where('started_at', '<=', $today->endOfDay())
            ->get(['started_at', 'trimp']);

        $daily = [];
        foreach ($activities as $activity) {
            $key = $activity->started_at->toDateString();
            $daily[$key] = ($daily[$key] ?? 0.0) + (float) ($activity->trimp ?? 0);
        }

        return $daily;
    }

    /**
     * @return array<string, float> date => estimated TRIMP
     */
    private function plannedLoad(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $workouts = $user->plannedWorkouts()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get(['date', 'workout_type', 'duration_min']);

        $daily = [];
        foreach ($workouts as $workout) {
            $minutes = $workout->duration_min ?? 0;
            if ($minutes <= 0) {
                continue;
            }

            $perMinute = $workout->workout_type?->estimatedTrimpPerMinute() ?? 1.0;
            $key = $workout->date->toDateString();
            $daily[$key] = ($daily[$key] ?? 0.0) + $minutes * $perMinute;
        }

        return $daily;
    }

    private function firstActivityDate(User $user): ?CarbonImmutable
    {
        $first = $user->activities()->orderBy('started_at')->value('started_at');

        return $first instanceof DateTimeInterface
            ? CarbonImmutable::parse($first)->startOfDay()
            : null;
    }

    private function lambda(int $days): float
    {
        return 1.0 - exp(-1.0 / $days);
    }
}
