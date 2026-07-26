<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Models\Activity;
use App\Models\User;

class RouteLeaderboardService
{
    /**
     * Group the athlete's activities by route and rank their efforts on each
     * repeated route (two or more runs of the same loop), fastest first.
     *
     * @return list<array{route_key: string, name: string, sport: string, distance_m: float|null, count: int, efforts: list<array{activity_id: int, date: string, duration_s: int|null, rank: int, is_best: bool}>}>
     */
    public function boards(User $user): array
    {
        $grouped = $user->activities()
            ->whereNotNull('route_key')
            ->whereNotNull('duration_s')
            ->orderBy('started_at')
            ->get(['id', 'route_key', 'sport', 'started_at', 'duration_s', 'distance_m', 'raw_summary'])
            ->groupBy('route_key');

        $boards = [];
        foreach ($grouped as $routeKey => $activities) {
            if ($activities->count() < 2) {
                continue;
            }

            $ranked = $activities->sortBy('duration_s')->values();
            $efforts = [];
            foreach ($ranked as $index => $activity) {
                $efforts[] = [
                    'activity_id' => $activity->id,
                    'date' => $activity->started_at->toDateString(),
                    'duration_s' => $activity->duration_s,
                    'rank' => $index + 1,
                    'is_best' => $index === 0,
                ];
            }

            $first = $ranked->first();
            $boards[] = [
                'route_key' => (string) $routeKey,
                'name' => $this->routeName($first),
                'sport' => $first->sport->value,
                'distance_m' => $first->distance_m,
                'count' => $ranked->count(),
                'efforts' => $efforts,
            ];
        }

        usort($boards, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $boards;
    }

    private function routeName(Activity $activity): string
    {
        $name = $activity->raw_summary['activityName'] ?? null;
        if (is_string($name) && $name !== '') {
            return $name;
        }

        return $activity->distance_m !== null
            ? number_format($activity->distance_m / 1000, 1).' km route'
            : 'Route';
    }
}
