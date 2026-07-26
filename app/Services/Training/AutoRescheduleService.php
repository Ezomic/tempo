<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Models\PlannedWorkout;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AutoRescheduleService
{
    /**
     * When a key session earlier this week was missed, propose the best open
     * day in the rest of the week to move it to, keeping hard days apart.
     * Suggestion only; nothing is moved until apply() is called.
     *
     * @return array{missed: array{id: int, title: string, date: string}, proposed_date: string, reason: string}|null
     */
    public function suggestion(User $user, CarbonImmutable $today): ?array
    {
        $weekStart = $today->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->addDays(6);

        $week = $user->plannedWorkouts()
            ->whereBetween('date', [$weekStart->toDateString().' 00:00:00', $weekEnd->toDateString().' 23:59:59'])
            ->orderBy('date')
            ->get();

        $missed = $this->missedKeySession($user, $week, $today);
        if ($missed === null) {
            return null;
        }

        $proposed = $this->bestOpenDay($week, $missed, $today, $weekEnd);
        if ($proposed === null) {
            return null;
        }

        return [
            'missed' => [
                'id' => $missed->id,
                'title' => $missed->title,
                'date' => $missed->date->toDateString(),
            ],
            'proposed_date' => $proposed->toDateString(),
            'reason' => "You missed {$missed->title} on {$missed->date->toDateString()}. Move it to {$proposed->toDateString()} to keep the week on track.",
        ];
    }

    /**
     * Move the missed session to the proposed day. Returns false when there is
     * nothing to reschedule.
     */
    public function apply(User $user, CarbonImmutable $today): bool
    {
        $suggestion = $this->suggestion($user, $today);
        if ($suggestion === null) {
            return false;
        }

        $workout = $user->plannedWorkouts()->find($suggestion['missed']['id']);
        if ($workout === null) {
            return false;
        }

        $workout->forceFill(['date' => $suggestion['proposed_date']])->save();

        return true;
    }

    /**
     * @param  Collection<int, PlannedWorkout>  $week
     */
    private function missedKeySession(User $user, $week, CarbonImmutable $today): ?PlannedWorkout
    {
        return $week
            ->filter(fn (PlannedWorkout $w): bool => $w->workout_type?->isHard() ?? false)
            ->filter(fn (PlannedWorkout $w): bool => $w->date->lessThan($today->startOfDay()))
            ->first(fn (PlannedWorkout $w): bool => ! $this->hasActivityOn($user, $w->date->toDateString()));
    }

    /**
     * @param  Collection<int, PlannedWorkout>  $week
     */
    private function bestOpenDay($week, PlannedWorkout $missed, CarbonImmutable $today, CarbonImmutable $weekEnd): ?CarbonImmutable
    {
        $occupied = [];
        $hardDates = [];
        foreach ($week as $workout) {
            if ($workout->id === $missed->id) {
                continue; // this one is moving
            }
            $date = $workout->date->toDateString();
            $occupied[$date] = true;
            if ($workout->workout_type?->isHard() ?? false) {
                $hardDates[$date] = true;
            }
        }

        for ($cursor = $today->startOfDay(); $cursor->lessThanOrEqualTo($weekEnd); $cursor = $cursor->addDay()) {
            $date = $cursor->toDateString();
            if (isset($occupied[$date])) {
                continue;
            }
            if (isset($hardDates[$cursor->subDay()->toDateString()]) || isset($hardDates[$cursor->addDay()->toDateString()])) {
                continue; // would sit next to another hard day
            }

            return $cursor;
        }

        return null;
    }

    private function hasActivityOn(User $user, string $date): bool
    {
        return $user->activities()
            ->whereBetween('started_at', [$date.' 00:00:00', $date.' 23:59:59'])
            ->exists();
    }
}
