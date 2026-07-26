<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Models\User;
use Carbon\CarbonImmutable;

class CardiacCostService
{
    /**
     * Weekly average cardiac cost (beats/km) per sport over the window.
     * Lower is better, so a falling line means improving aerobic economy.
     *
     * @return array<string, list<array{week_start: string, cost: float}>>
     */
    public function weeklyTrend(User $user, CarbonImmutable $today, int $weeks): array
    {
        $from = $today->subWeeks($weeks - 1)->startOfWeek();

        $activities = $user->activities()
            ->whereNotNull('cardiac_cost')
            ->where('started_at', '>=', $from->toDateString())
            ->orderBy('started_at')
            ->get(['sport', 'started_at', 'cardiac_cost']);

        /** @var array<string, array<string, list<float>>> $buckets */
        $buckets = [];
        foreach ($activities as $activity) {
            $sport = $activity->sport->value;
            $week = CarbonImmutable::parse($activity->started_at->toDateString())->startOfWeek()->toDateString();
            $buckets[$sport][$week][] = (float) $activity->cardiac_cost;
        }

        $trend = [];
        foreach ($buckets as $sport => $weeksData) {
            $points = [];
            foreach ($weeksData as $week => $values) {
                $points[] = ['week_start' => $week, 'cost' => round(array_sum($values) / count($values), 1)];
            }
            $trend[$sport] = $points;
        }

        return $trend;
    }
}
