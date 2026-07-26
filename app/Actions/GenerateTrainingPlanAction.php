<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\WorkoutType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

class GenerateTrainingPlanAction
{
    private const MIN_WEEKLY_LOAD = 150.0;

    private const PROGRESS_RAMP = 1.06;

    /** Preferred weekday per session slot: long run first on Saturday. */
    private const DAY_ORDER = [Carbon::SATURDAY, Carbon::SUNDAY, Carbon::TUESDAY, Carbon::THURSDAY, Carbon::WEDNESDAY, Carbon::MONDAY];

    /** @var array<string, list<WorkoutType>> */
    private const PHASE_SESSIONS = [
        'base' => [WorkoutType::Long, WorkoutType::Easy, WorkoutType::Endurance, WorkoutType::Easy, WorkoutType::Easy, WorkoutType::Recovery],
        'build' => [WorkoutType::Long, WorkoutType::Tempo, WorkoutType::Easy, WorkoutType::Endurance, WorkoutType::Easy, WorkoutType::Recovery],
        'peak' => [WorkoutType::Long, WorkoutType::Intervals, WorkoutType::Tempo, WorkoutType::Easy, WorkoutType::Easy, WorkoutType::Recovery],
        'taper' => [WorkoutType::Endurance, WorkoutType::Intervals, WorkoutType::Easy, WorkoutType::Easy, WorkoutType::Recovery, WorkoutType::Recovery],
    ];

    /** @var array<string, float> */
    private const SESSION_WEIGHT = [
        'long' => 1.6, 'intervals' => 1.2, 'tempo' => 1.2, 'endurance' => 1.0, 'easy' => 0.8, 'recovery' => 0.5,
    ];

    /**
     * Build a periodized plan from today to the race, respecting current
     * fitness and ramping weekly load within safe bounds.
     *
     * @param  list<string>  $busyDates  Y-m-d the athlete is booked; key sessions avoid them
     * @return list<array{date: string, workout_type: string|null, duration_min: int, title: string, phase: string}>
     */
    public function handle(CarbonImmutable $start, CarbonImmutable $raceDate, int $sessionsPerWeek, float $currentCtl, array $busyDates = []): array
    {
        $busy = array_flip($busyDates);

        $firstMonday = $start->startOfWeek(Carbon::MONDAY);
        $raceMonday = $raceDate->startOfWeek(Carbon::MONDAY);
        $totalWeeks = (int) $firstMonday->diffInWeeks($raceMonday) + 1;

        if ($totalWeeks < 1) {
            return [];
        }

        $loads = $this->weeklyLoads($totalWeeks, $currentCtl);
        $sessions = [];

        for ($w = 0; $w < $totalWeeks; $w++) {
            $weekStart = $firstMonday->addWeeks($w);
            $phase = $this->phase($totalWeeks - $w, $totalWeeks);
            $sessions = array_merge($sessions, $this->weekSessions($weekStart, $raceDate, $phase, $sessionsPerWeek, $loads[$w], $start, $busy));
        }

        $sessions[] = [
            'date' => $raceDate->toDateString(),
            'workout_type' => null,
            'duration_min' => 0,
            'title' => 'Race day',
            'phase' => 'race',
        ];

        return $sessions;
    }

    /**
     * @return list<float>
     */
    private function weeklyLoads(int $totalWeeks, float $currentCtl): array
    {
        $current = max($currentCtl * 7, self::MIN_WEEKLY_LOAD);
        $loads = [];

        for ($w = 0; $w < $totalWeeks; $w++) {
            $weeksToRace = $totalWeeks - $w;

            if ($weeksToRace <= 2) {
                $loads[] = $current * ($weeksToRace === 2 ? 0.6 : 0.4);
            } elseif (($w + 1) % 4 === 0) {
                $loads[] = $current * 0.7; // recovery week, base unchanged
            } else {
                $current *= self::PROGRESS_RAMP;
                $loads[] = $current;
            }
        }

        return $loads;
    }

    private function phase(int $weeksToRace, int $totalWeeks): string
    {
        if ($weeksToRace <= 2) {
            return 'taper';
        }
        if ($weeksToRace <= 5) {
            return 'peak';
        }

        return $weeksToRace > $totalWeeks - max(1, (int) floor($totalWeeks * 0.35)) ? 'base' : 'build';
    }

    /**
     * @param  array<string, int>  $busy
     * @return list<array{date: string, workout_type: string|null, duration_min: int, title: string, phase: string}>
     */
    private function weekSessions(CarbonImmutable $weekStart, CarbonImmutable $raceDate, string $phase, int $sessionsPerWeek, float $weeklyLoad, CarbonImmutable $start, array $busy): array
    {
        $types = array_slice(self::PHASE_SESSIONS[$phase], 0, $sessionsPerWeek);
        $totalWeight = array_sum(array_map(fn (WorkoutType $t): float => self::SESSION_WEIGHT[$t->value], $types));

        if ($totalWeight <= 0.0) {
            return [];
        }

        $sessions = [];
        foreach ($types as $i => $type) {
            $date = $weekStart->addDays((self::DAY_ORDER[$i] - Carbon::MONDAY + 7) % 7);
            if ($date->lessThan($start->startOfDay()) || $date->greaterThanOrEqualTo($raceDate)) {
                continue; // don't plan in the past or on/after race day
            }

            // A key session on a booked day is eased to recovery so it doesn't
            // collide with a full calendar.
            if (isset($busy[$date->toDateString()]) && $type->isHard()) {
                $type = WorkoutType::Recovery;
            }

            $load = $weeklyLoad * (self::SESSION_WEIGHT[$type->value] / $totalWeight);
            $duration = (int) max(20, min(150, round($load / $type->estimatedTrimpPerMinute())));

            $sessions[] = [
                'date' => $date->toDateString(),
                'workout_type' => $type->value,
                'duration_min' => $duration,
                'title' => ucfirst($phase).' · '.$type->label(),
                'phase' => $phase,
            ];
        }

        return $sessions;
    }
}
