<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Models\Activity;
use App\Models\PlannedWorkout;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AdherenceService
{
    private const MOVE_WINDOW_DAYS = 2;

    /**
     * Weekly plan compliance, oldest week first. Each planned session resolves
     * to completed / modified / moved / skipped against actual activities, and
     * a moved session is matched to a nearby activity rather than double-counted
     * as a skip plus an extra.
     *
     * @return list<array{week_start: string, total: int, completed: int, modified: int, moved: int, skipped: int, adherence_pct: int|null, slipped: list<array{date: string, title: string}>}>
     */
    public function weekly(User $user, CarbonImmutable $today, int $weeks): array
    {
        $firstWeekStart = $today->startOfWeek(Carbon::MONDAY)->subWeeks($weeks - 1);

        $result = [];
        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = $firstWeekStart->addWeeks($i);
            $result[] = $this->week($user, $weekStart);
        }

        return $result;
    }

    /**
     * @return array{week_start: string, total: int, completed: int, modified: int, moved: int, skipped: int, adherence_pct: int|null, slipped: list<array{date: string, title: string}>}
     */
    private function week(User $user, CarbonImmutable $weekStart): array
    {
        $weekEnd = $weekStart->endOfWeek(Carbon::SUNDAY);

        $planned = $user->plannedWorkouts()
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('date')
            ->get();

        $activities = $user->activities()
            ->whereBetween('started_at', [
                $weekStart->subDays(self::MOVE_WINDOW_DAYS),
                $weekEnd->addDays(self::MOVE_WINDOW_DAYS),
            ])
            ->get(['id', 'sport', 'started_at']);

        $counts = ['completed' => 0, 'modified' => 0, 'moved' => 0, 'skipped' => 0];
        $consumed = [];
        $slipped = [];

        foreach ($planned as $workout) {
            $status = $this->resolve($workout, $activities, $consumed);
            $counts[$status]++;

            if ($status === 'skipped') {
                $slipped[] = ['date' => $workout->date->toDateString(), 'title' => $workout->title];
            }
        }

        $total = $planned->count();
        $done = $counts['completed'] + $counts['modified'] + $counts['moved'];

        return [
            'week_start' => $weekStart->toDateString(),
            'total' => $total,
            'completed' => $counts['completed'],
            'modified' => $counts['modified'],
            'moved' => $counts['moved'],
            'skipped' => $counts['skipped'],
            'adherence_pct' => $total > 0 ? (int) round($done / $total * 100) : null,
            'slipped' => $slipped,
        ];
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @param  array<int, true>  $consumed
     */
    private function resolve(PlannedWorkout $workout, Collection $activities, array &$consumed): string
    {
        $date = $workout->date->startOfDay();

        $sameDay = $this->match($activities, $consumed, $workout, $date, 0);
        if ($sameDay !== null) {
            $consumed[$sameDay] = true;

            return $workout->adapted_at !== null ? 'modified' : 'completed';
        }

        $nearby = $this->match($activities, $consumed, $workout, $date, self::MOVE_WINDOW_DAYS);
        if ($nearby !== null) {
            $consumed[$nearby] = true;

            return 'moved';
        }

        return 'skipped';
    }

    /**
     * The id of the closest unconsumed activity of the same sport within the
     * day tolerance, or null.
     *
     * @param  Collection<int, Activity>  $activities
     * @param  array<int, true>  $consumed
     */
    private function match(Collection $activities, array $consumed, PlannedWorkout $workout, CarbonInterface $date, int $tolerance): ?int
    {
        $best = null;
        $bestDelta = null;

        foreach ($activities as $activity) {
            if (isset($consumed[$activity->id]) || $activity->sport !== $workout->sport) {
                continue;
            }

            $delta = abs($activity->started_at->startOfDay()->diffInDays($date));
            if ($delta > $tolerance) {
                continue;
            }

            if ($bestDelta === null || $delta < $bestDelta) {
                $best = $activity->id;
                $bestDelta = $delta;
            }
        }

        return $best;
    }
}
