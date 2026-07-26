<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

class ZoneDistributionService
{
    private const ZONES = [1, 2, 3, 4, 5];

    /**
     * Weekly time-in-zone (seconds), oldest week first. Each week's zone
     * seconds sum to its total, which reconciles the distribution.
     *
     * @return list<array{week_start: string, zones: array<int, int>, total: int}>
     */
    public function weekly(User $user, CarbonImmutable $today, int $weeks): array
    {
        $firstWeekStart = $today->startOfWeek(Carbon::MONDAY)->subWeeks($weeks - 1);
        $activities = $user->activities()
            ->whereBetween('started_at', [$firstWeekStart, $today->endOfDay()])
            ->get(['started_at', 'hr_zone_seconds']);

        $buckets = [];
        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = $firstWeekStart->addWeeks($i)->toDateString();
            $buckets[$weekStart] = [
                'week_start' => $weekStart,
                'zones' => array_fill_keys(self::ZONES, 0),
                'total' => 0,
            ];
        }

        foreach ($activities as $activity) {
            $weekKey = $activity->started_at->startOfWeek(Carbon::MONDAY)->toDateString();
            if (! isset($buckets[$weekKey])) {
                continue;
            }

            foreach (self::ZONES as $zone) {
                $seconds = (int) (($activity->hr_zone_seconds ?? [])[$zone] ?? 0);
                $buckets[$weekKey]['zones'][$zone] += $seconds;
                $buckets[$weekKey]['total'] += $seconds;
            }
        }

        return array_values($buckets);
    }

    /**
     * Easy (Z1-2) / moderate (Z3) / hard (Z4-5) split over the window, with an
     * 80/20 verdict against the configured easy target.
     *
     * @return array{easy_pct: float|null, moderate_pct: float|null, hard_pct: float|null, total_seconds: int, verdict: string, easy_target: float}
     */
    public function polarization(User $user, CarbonImmutable $today, int $weeks): array
    {
        $start = $today->startOfWeek(Carbon::MONDAY)->subWeeks($weeks - 1);
        $activities = $user->activities()
            ->whereBetween('started_at', [$start, $today->endOfDay()])
            ->get(['hr_zone_seconds']);

        $easy = 0;
        $moderate = 0;
        $hard = 0;
        foreach ($activities as $activity) {
            $zones = $activity->hr_zone_seconds ?? [];
            $easy += (int) ($zones[1] ?? 0) + (int) ($zones[2] ?? 0);
            $moderate += (int) ($zones[3] ?? 0);
            $hard += (int) ($zones[4] ?? 0) + (int) ($zones[5] ?? 0);
        }

        $total = $easy + $moderate + $hard;
        $target = (float) config('training.polarization.easy_target');

        if ($total === 0) {
            return [
                'easy_pct' => null,
                'moderate_pct' => null,
                'hard_pct' => null,
                'total_seconds' => 0,
                'verdict' => 'unknown',
                'easy_target' => $target,
            ];
        }

        $easyPct = round($easy / $total * 100, 1);

        return [
            'easy_pct' => $easyPct,
            'moderate_pct' => round($moderate / $total * 100, 1),
            'hard_pct' => round($hard / $total * 100, 1),
            'total_seconds' => $total,
            'verdict' => $easyPct >= $target ? 'on_target' : 'too_much_intensity',
            'easy_target' => $target,
        ];
    }
}
