<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

class ConsistencyService
{
    /**
     * A per-day training-intensity grid for the trailing year plus streak
     * counts. Days are bucketed 0 to 4 by load so a heatmap can colour them.
     *
     * @return array{days: list<array{date: string, load: float, level: int}>, current_streak: int, longest_streak: int, active_days: int}
     */
    public function heatmap(User $user, CarbonImmutable $today, int $days = 364): array
    {
        $start = $today->subDays($days)->startOfWeek(Carbon::MONDAY);

        $loadByDate = $this->loadByDate($user, $start, $today);

        $grid = [];
        for ($cursor = $start; $cursor->lessThanOrEqualTo($today); $cursor = $cursor->addDay()) {
            $key = $cursor->toDateString();
            $load = $loadByDate[$key] ?? 0.0;
            $grid[] = ['date' => $key, 'load' => round($load, 1), 'level' => $this->level($load)];
        }

        return [
            'days' => $grid,
            'current_streak' => $this->currentStreak($loadByDate, $today),
            'longest_streak' => $this->longestStreak($grid),
            'active_days' => count(array_filter($loadByDate, fn (float $l): bool => $l > 0.0)),
        ];
    }

    /**
     * @return array<string, float>
     */
    private function loadByDate(User $user, CarbonImmutable $start, CarbonImmutable $today): array
    {
        $activities = $user->activities()
            ->whereBetween('started_at', [$start->startOfDay(), $today->endOfDay()])
            ->get(['started_at', 'trimp', 'duration_s']);

        $byDate = [];
        foreach ($activities as $activity) {
            $key = $activity->started_at->toDateString();
            // Use TRIMP where present, else a nominal value so the day still counts.
            $value = (float) ($activity->trimp ?? 0);
            if ($value <= 0.0 && ($activity->duration_s ?? 0) > 0) {
                $value = 1.0;
            }
            $byDate[$key] = ($byDate[$key] ?? 0.0) + $value;
        }

        return $byDate;
    }

    private function level(float $load): int
    {
        return match (true) {
            $load <= 0.0 => 0,
            $load < 30 => 1,
            $load < 60 => 2,
            $load < 100 => 3,
            default => 4,
        };
    }

    /**
     * Consecutive active days ending today, or yesterday if today is a rest day.
     *
     * @param  array<string, float>  $loadByDate
     */
    private function currentStreak(array $loadByDate, CarbonImmutable $today): int
    {
        $anchor = ($loadByDate[$today->toDateString()] ?? 0.0) > 0.0
            ? $today
            : $today->subDay();

        if (($loadByDate[$anchor->toDateString()] ?? 0.0) <= 0.0) {
            return 0;
        }

        $streak = 0;
        for ($cursor = $anchor; ($loadByDate[$cursor->toDateString()] ?? 0.0) > 0.0; $cursor = $cursor->subDay()) {
            $streak++;
        }

        return $streak;
    }

    /**
     * @param  list<array{date: string, load: float, level: int}>  $grid
     */
    private function longestStreak(array $grid): int
    {
        $longest = 0;
        $run = 0;
        foreach ($grid as $day) {
            if ($day['load'] > 0.0) {
                $run++;
                $longest = max($longest, $run);
            } else {
                $run = 0;
            }
        }

        return $longest;
    }
}
