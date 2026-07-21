<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Enums\Sport;
use App\Models\Activity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TrainingLoadService
{
    private const ACUTE_DAYS = 7;

    private const CHRONIC_DAYS = 28;

    /**
     * Acute (7-day) vs chronic (28-day weekly average) load and their ratio.
     *
     * @return array{acute: float, chronic_weekly: float, ratio: float|null, status: string}
     */
    public function acuteChronic(User $user, CarbonImmutable $today): array
    {
        $windowStart = $today->subDays(self::CHRONIC_DAYS - 1)->startOfDay();
        $acuteStart = $today->subDays(self::ACUTE_DAYS - 1)->startOfDay();

        $activities = $this->activitiesSince($user, $windowStart, $today->endOfDay());

        $acute = 0.0;
        $chronic = 0.0;
        foreach ($activities as $activity) {
            $load = (float) ($activity->trimp ?? 0);
            $chronic += $load;
            if ($activity->started_at->greaterThanOrEqualTo($acuteStart)) {
                $acute += $load;
            }
        }

        $chronicWeekly = $chronic / (self::CHRONIC_DAYS / 7);
        $ratio = $chronicWeekly > 0 ? round($acute / $chronicWeekly, 2) : null;

        return [
            'acute' => round($acute, 1),
            'chronic_weekly' => round($chronicWeekly, 1),
            'ratio' => $ratio,
            'status' => $this->ratioStatus($ratio),
        ];
    }

    /**
     * Per-week TRIMP totals split by sport, oldest week first.
     *
     * @return list<array{week_start: string, run: float, bike: float, other: float, total: float}>
     */
    public function weeklyBySport(User $user, CarbonImmutable $today, int $weeks): array
    {
        $firstWeekStart = $today->startOfWeek(Carbon::MONDAY)->subWeeks($weeks - 1);
        $activities = $this->activitiesSince($user, $firstWeekStart, $today->endOfDay());

        $buckets = [];
        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = $firstWeekStart->addWeeks($i);
            $buckets[$weekStart->toDateString()] = [
                'week_start' => $weekStart->toDateString(),
                'run' => 0.0,
                'bike' => 0.0,
                'other' => 0.0,
                'total' => 0.0,
            ];
        }

        foreach ($activities as $activity) {
            $weekKey = $activity->started_at->startOfWeek(Carbon::MONDAY)->toDateString();
            if (! isset($buckets[$weekKey])) {
                continue;
            }

            $load = (float) ($activity->trimp ?? 0);
            $key = match ($activity->sport) {
                Sport::Run => 'run',
                Sport::Bike => 'bike',
                Sport::Other => 'other',
            };
            $buckets[$weekKey][$key] += $load;
            $buckets[$weekKey]['total'] += $load;
        }

        return array_values(array_map(
            fn (array $week): array => [
                'week_start' => $week['week_start'],
                'run' => round($week['run'], 1),
                'bike' => round($week['bike'], 1),
                'other' => round($week['other'], 1),
                'total' => round($week['total'], 1),
            ],
            $buckets,
        ));
    }

    /**
     * @return Collection<int, Activity>
     */
    private function activitiesSince(User $user, CarbonImmutable $from, CarbonImmutable $to)
    {
        return $user->activities()
            ->whereBetween('started_at', [$from, $to])
            ->get(['id', 'sport', 'trimp', 'started_at']);
    }

    private function ratioStatus(?float $ratio): string
    {
        return match (true) {
            $ratio === null => 'unknown',
            $ratio < 0.8 => 'low',
            $ratio > 1.3 => 'high',
            default => 'optimal',
        };
    }
}
