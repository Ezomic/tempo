<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Enums\GoalType;
use App\Models\TrainingGoal;
use App\Models\User;
use App\Support\Payload;
use Carbon\CarbonImmutable;

class TaperReadinessService
{
    public function __construct(private readonly ReadinessService $readiness) {}

    /**
     * Race-week taper read, or null when no race goal falls inside the taper
     * window. Grades three factors (freshness, load drop, readiness) and takes
     * the worst as the overall verdict.
     *
     * @return array{race_date: string, days_to_race: int, verdict: string, summary: string, factors: list<array{key: string, label: string, state: string, detail: string}>}|null
     */
    public function forNextRace(User $user, CarbonImmutable $today): ?array
    {
        $race = $this->nextRace($user, $today);
        if ($race === null) {
            return null;
        }

        $daysToRace = (int) $today->startOfDay()->diffInDays($race->target_date->startOfDay(), false);

        $factors = [
            $this->freshnessFactor($user),
            $this->loadDropFactor($user, $today),
            $this->readinessFactor($user),
        ];

        $verdict = $this->worst($factors);

        return [
            'race_date' => $race->target_date->toDateString(),
            'days_to_race' => $daysToRace,
            'verdict' => $verdict,
            'summary' => $this->summary($verdict, $daysToRace),
            'factors' => $factors,
        ];
    }

    private function nextRace(User $user, CarbonImmutable $today): ?TrainingGoal
    {
        $window = Payload::toInt(config('training.taper.window_days'));

        return $user->trainingGoals()
            ->where('type', GoalType::RaceTime)
            ->whereDate('target_date', '>=', $today->toDateString())
            ->whereDate('target_date', '<=', $today->addDays($window)->toDateString())
            ->orderBy('target_date')
            ->first();
    }

    /**
     * @return array{key: string, label: string, state: string, detail: string}
     */
    private function freshnessFactor(User $user): array
    {
        $tsb = $user->dailyLoadMetrics()->orderByDesc('date')->value('tsb');
        $min = Payload::toFloat(config('training.taper.tsb_min'));
        $max = Payload::toFloat(config('training.taper.tsb_max'));

        if ($tsb === null) {
            return $this->factor('freshness', 'Freshness (form)', 'watch', 'No fitness data yet.');
        }

        $tsb = Payload::toFloat($tsb);
        $state = match (true) {
            $tsb < 0 => 'off',
            $tsb >= $min && $tsb <= $max => 'good',
            default => 'watch',
        };

        $detail = match ($state) {
            'good' => "Form is in the race window (TSB {$tsb}).",
            'off' => "Still fatigued (TSB {$tsb}); form is negative.",
            default => "Form is at TSB {$tsb}; aim for {$min} to {$max}.",
        };

        return $this->factor('freshness', 'Freshness (form)', $state, $detail);
    }

    /**
     * @return array{key: string, label: string, state: string, detail: string}
     */
    private function loadDropFactor(User $user, CarbonImmutable $today): array
    {
        $thisWeek = $this->trimpBetween($user, $today->subDays(6), $today);
        $lastWeek = $this->trimpBetween($user, $today->subDays(13), $today->subDays(7));

        if ($lastWeek <= 0.0) {
            return $this->factor('load_drop', 'Load drop', 'watch', 'Not enough load history to compare.');
        }

        $ratio = $thisWeek / $lastWeek;
        $state = match (true) {
            $ratio <= 0.85 => 'good',
            $ratio <= 1.0 => 'watch',
            default => 'off',
        };

        $pct = (int) round((1 - $ratio) * 100);
        $detail = match ($state) {
            'good' => "Weekly load is down {$pct}% on last week.",
            'watch' => 'Weekly load is only slightly lower than last week.',
            default => 'Weekly load is higher than last week; ease off.',
        };

        return $this->factor('load_drop', 'Load drop', $state, $detail);
    }

    /**
     * @return array{key: string, label: string, state: string, detail: string}
     */
    private function readinessFactor(User $user): array
    {
        $snapshot = $this->readiness->snapshot($user, null);
        $score = $snapshot['score'] ?? null;

        if ($score === null) {
            return $this->factor('readiness', 'Readiness', 'watch', 'No readiness data yet.');
        }

        $state = match (true) {
            $score >= 70 => 'good',
            $score >= 55 => 'watch',
            default => 'off',
        };

        return $this->factor('readiness', 'Readiness', $state, "Readiness is {$score}/100.");
    }

    private function trimpBetween(User $user, CarbonImmutable $from, CarbonImmutable $to): float
    {
        return (float) $user->dailyLoadMetrics()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->sum('trimp');
    }

    /**
     * @param  list<array{key: string, label: string, state: string, detail: string}>  $factors
     */
    private function worst(array $factors): string
    {
        $rank = ['good' => 0, 'watch' => 1, 'off' => 2];
        $worst = 'good';
        foreach ($factors as $factor) {
            if ($rank[$factor['state']] > $rank[$worst]) {
                $worst = $factor['state'];
            }
        }

        return $worst;
    }

    private function summary(string $verdict, int $daysToRace): string
    {
        $when = $daysToRace <= 0 ? 'today' : "in {$daysToRace} days";

        return match ($verdict) {
            'good' => "Taper is landing well for your race {$when}.",
            'watch' => "Taper is mostly on track for your race {$when}; a couple of things to watch.",
            default => "Your taper is off track for your race {$when}.",
        };
    }

    /**
     * @return array{key: string, label: string, state: string, detail: string}
     */
    private function factor(string $key, string $label, string $state, string $detail): array
    {
        return ['key' => $key, 'label' => $label, 'state' => $state, 'detail' => $detail];
    }
}
