<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Models\User;
use Carbon\CarbonImmutable;

class EfficiencyFactorService
{
    /**
     * Weekly average efficiency factor per sport over the recent window.
     * Only sports with at least one scored session appear.
     *
     * @return array<string, list<array{week_start: string, ef: float}>>
     */
    public function weeklyTrend(User $user, CarbonImmutable $today, int $weeks): array
    {
        $from = $today->subWeeks($weeks - 1)->startOfWeek();

        $activities = $user->activities()
            ->whereNotNull('efficiency_factor')
            ->where('started_at', '>=', $from->toDateString())
            ->orderBy('started_at')
            ->get(['sport', 'started_at', 'efficiency_factor']);

        /** @var array<string, array<string, list<float>>> $buckets */
        $buckets = [];
        foreach ($activities as $activity) {
            $sport = $activity->sport->value;
            $week = CarbonImmutable::parse($activity->started_at->toDateString())->startOfWeek()->toDateString();
            $buckets[$sport][$week][] = (float) $activity->efficiency_factor;
        }

        $trend = [];
        foreach ($buckets as $sport => $weeksData) {
            $points = [];
            foreach ($weeksData as $week => $values) {
                $points[] = ['week_start' => $week, 'ef' => round(array_sum($values) / count($values), 2)];
            }
            $trend[$sport] = $points;
        }

        return $trend;
    }
}
