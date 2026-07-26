<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\PlannedWorkout;
use App\Models\User;
use App\Services\Training\AdherenceService;
use App\Services\Training\FitnessCurveService;
use App\Services\Training\TrainingLoadService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

class BuildWeeklyDigestAction
{
    public function __construct(
        private readonly TrainingLoadService $load,
        private readonly FitnessCurveService $fitnessCurve,
        private readonly AdherenceService $adherence,
    ) {}

    /**
     * Assemble the Monday digest for a user: the week just finished plus a look
     * at the week ahead, from the same sources as the in-app views.
     *
     * @return array<string, mixed>
     */
    public function handle(User $user, CarbonImmutable $today): array
    {
        $lastWeekStart = $today->startOfWeek(Carbon::MONDAY)->subWeek();
        $lastWeekEnd = $lastWeekStart->endOfWeek(Carbon::SUNDAY);

        $sessions = $user->activities()
            ->whereBetween('started_at', [$lastWeekStart, $lastWeekEnd])
            ->count();

        $week = $this->load->weeklyBySport($user, $lastWeekEnd, 1)[0];

        return [
            'user_name' => $user->name,
            'week_label' => $lastWeekStart->format('j M').' – '.$lastWeekEnd->format('j M'),
            'has_activity' => $sessions > 0,
            'sessions' => $sessions,
            'load' => [
                'total' => (int) round($week['total']),
                'run' => (int) round($week['run']),
                'bike' => (int) round($week['bike']),
            ],
            'form' => $this->form($user, $today),
            'prs' => $this->personalRecords($user, $lastWeekStart, $lastWeekEnd),
            'adherence' => $this->adherenceSummary($user, $lastWeekEnd),
            'next_week' => $this->nextWeek($user, $today),
        ];
    }

    /**
     * @return array{ctl: float|null, tsb: float|null, trend: string}
     */
    private function form(User $user, CarbonImmutable $today): array
    {
        $series = $this->fitnessCurve->series($user, $today->subDays(8), $today);

        if ($series === []) {
            return ['ctl' => null, 'tsb' => null, 'trend' => 'flat'];
        }

        $latest = $series[array_key_last($series)];
        $first = $series[0];
        $trend = match (true) {
            $latest['ctl'] > $first['ctl'] + 1 => 'rising',
            $latest['ctl'] < $first['ctl'] - 1 => 'falling',
            default => 'flat',
        };

        return ['ctl' => $latest['ctl'], 'tsb' => $latest['tsb'], 'trend' => $trend];
    }

    /**
     * @return list<array{label: string, time: string}>
     */
    private function personalRecords(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return array_values($user->personalRecords()
            ->whereBetween('achieved_on', [$from->toDateString(), $to->toDateString()])
            ->orderBy('distance_m')
            ->get()
            ->map(fn ($record): array => [
                'label' => $this->distanceLabel($record->distance_m),
                'time' => $this->formatTime($record->duration_s),
            ])
            ->all());
    }

    /**
     * @return array{pct: int|null, completed: int, skipped: int}
     */
    private function adherenceSummary(User $user, CarbonImmutable $lastWeekEnd): array
    {
        $week = $this->adherence->weekly($user, $lastWeekEnd, 1)[0];

        return [
            'pct' => $week['adherence_pct'],
            'completed' => $week['completed'] + $week['modified'] + $week['moved'],
            'skipped' => $week['skipped'],
        ];
    }

    /**
     * @return array{count: int, sessions: list<array{date: string, title: string}>}
     */
    private function nextWeek(User $user, CarbonImmutable $today): array
    {
        $start = $today->startOfWeek(Carbon::MONDAY);
        $end = $start->endOfWeek(Carbon::SUNDAY);

        $sessions = array_values($user->plannedWorkouts()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->get()
            ->map(fn (PlannedWorkout $workout): array => [
                'date' => $workout->date->format('D j M'),
                'title' => $workout->title,
            ])
            ->all());

        return ['count' => count($sessions), 'sessions' => $sessions];
    }

    private function distanceLabel(int $metres): string
    {
        return match ($metres) {
            1000 => '1K',
            5000 => '5K',
            10000 => '10K',
            21097 => 'Half marathon',
            42195 => 'Marathon',
            default => number_format($metres / 1000, 1).'K',
        };
    }

    private function formatTime(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
    }
}
