<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Enums\HrvStatus;
use App\Models\User;
use App\Models\WellnessDay;
use Carbon\CarbonImmutable;

class ReadinessService
{
    private const READY = 'ready';

    private const CAUTION = 'caution';

    private const REST = 'rest';

    /**
     * Recovery data older than this is history, not a verdict on today. Garmin
     * data lands the morning after a night's sleep, so one day behind is normal
     * and anything beyond that means sync has stopped.
     */
    public const STALE_AFTER_DAYS = 2;

    /**
     * Today-ish readiness from the most recent wellness day, tempered by how
     * hard recent training has been (the acute:chronic ratio). The score is
     * the sum of named contributors so it can be explained.
     *
     * @return array{score: int, verdict: string, hrv_status: string|null, body_battery: int|null, resting_hr: int|null, date: string, age_days: int, stale: bool, contributors: list<array{key: string, label: string, detail: string, impact: int, direction: string}>, summary: string}|null
     */
    public function snapshot(User $user, ?float $acwr): ?array
    {
        $day = $user->wellnessDays()->orderByDesc('date')->first();

        if ($day === null) {
            return null;
        }

        $contributors = $this->contributors($day, $acwr, $this->restingBaseline($user, $day));
        $score = $this->score($contributors);
        $ageDays = $this->ageInDays($day);
        $stale = $ageDays > self::STALE_AFTER_DAYS;

        return [
            'score' => $score,
            'verdict' => $this->verdict($day, $acwr),
            'hrv_status' => $day->hrv_status?->value,
            'body_battery' => $day->body_battery_high,
            'resting_hr' => $day->resting_hr,
            'date' => $day->date->toDateString(),
            'age_days' => $ageDays,
            'stale' => $stale,
            'contributors' => $contributors,
            'summary' => $stale
                ? "Recovery data is {$ageDays} days old, so this is not a reading on today."
                : $this->summary($score, $contributors),
        ];
    }

    /**
     * The score only when it still describes today. Everything that acts on
     * readiness rather than displaying it goes through this, so stale data is
     * treated as no data instead of a real signal.
     *
     * @param  array{score?: int, stale?: bool}|null  $snapshot
     */
    public function actionableScore(?array $snapshot): ?int
    {
        if ($snapshot === null || ($snapshot['stale'] ?? false)) {
            return null;
        }

        return $snapshot['score'] ?? null;
    }

    private function ageInDays(WellnessDay $day): int
    {
        return (int) max(0, $day->date
            ->startOfDay()
            ->diffInDays(CarbonImmutable::now()->startOfDay(), absolute: false));
    }

    /**
     * @param  list<array{key: string, label: string, detail: string, impact: int, direction: string}>  $contributors
     */
    private function score(array $contributors): int
    {
        $score = 100 + array_sum(array_map(fn (array $c): int => $c['impact'], $contributors));

        return max(0, min(100, $score));
    }

    /**
     * @return list<array{key: string, label: string, detail: string, impact: int, direction: string}>
     */
    private function contributors(WellnessDay $day, ?float $acwr, ?int $restingBaseline): array
    {
        $contributors = [];

        $contributors[] = $this->contributor('hrv', 'HRV', $this->hrvDetail($day->hrv_status), match ($day->hrv_status) {
            HrvStatus::Poor => -45,
            HrvStatus::Low => -30,
            HrvStatus::Unbalanced => -18,
            default => 0,
        });

        if ($day->body_battery_high !== null) {
            $contributors[] = $this->contributor('body_battery', 'Body battery', (string) $day->body_battery_high, match (true) {
                $day->body_battery_high < 25 => -22,
                $day->body_battery_high < 50 => -10,
                default => 0,
            });
        }

        if ($day->sleep_score !== null) {
            $contributors[] = $this->contributor('sleep', 'Sleep', (string) $day->sleep_score, match (true) {
                $day->sleep_score < 50 => -18,
                $day->sleep_score < 65 => -8,
                $day->sleep_score >= 80 => 5,
                default => 0,
            });
        }

        if ($restingBaseline !== null && $day->resting_hr !== null) {
            $delta = $day->resting_hr - $restingBaseline;
            $contributors[] = $this->contributor('resting_hr', 'Resting HR', "{$day->resting_hr} vs {$restingBaseline} avg", match (true) {
                $delta >= 7 => -10,
                $delta >= 3 => -5,
                default => 0,
            });
        }

        $contributors[] = $this->contributor('load', 'Training load', $acwr !== null ? "ACWR {$acwr}" : 'no data', match (true) {
            $acwr === null => 0,
            $acwr > 1.5 => -25,
            $acwr > 1.3 => -12,
            $acwr < 0.8 => -4,
            default => 0,
        });

        return $contributors;
    }

    /**
     * @return array{key: string, label: string, detail: string, impact: int, direction: string}
     */
    private function contributor(string $key, string $label, string $detail, int $impact): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'detail' => $detail,
            'impact' => $impact,
            'direction' => $impact < 0 ? 'down' : ($impact > 0 ? 'up' : 'neutral'),
        ];
    }

    /**
     * @param  list<array{key: string, label: string, detail: string, impact: int, direction: string}>  $contributors
     */
    private function summary(int $score, array $contributors): string
    {
        $negatives = array_values(array_filter($contributors, fn (array $c): bool => $c['impact'] < 0));
        usort($negatives, fn (array $a, array $b): int => $a['impact'] <=> $b['impact']);

        if ($negatives === []) {
            return "{$score}: everything looks good for training.";
        }

        $reasons = array_map(fn (array $c): string => $this->reason($c), array_slice($negatives, 0, 2));

        return "{$score}: ".$this->joinReasons($reasons).'.';
    }

    /**
     * @param  array{key: string, label: string, detail: string, impact: int, direction: string}  $contributor
     */
    private function reason(array $contributor): string
    {
        return match ($contributor['key']) {
            'hrv' => 'HRV is '.strtolower($contributor['detail']),
            'body_battery' => 'body battery is low',
            'sleep' => 'sleep was poor',
            'resting_hr' => 'resting HR is elevated',
            'load' => 'training load is high',
            default => strtolower($contributor['label']).' is off',
        };
    }

    /**
     * @param  list<string>  $reasons
     */
    private function joinReasons(array $reasons): string
    {
        return count($reasons) === 1 ? $reasons[0] : implode(' and ', $reasons);
    }

    private function hrvDetail(?HrvStatus $status): string
    {
        return match ($status) {
            HrvStatus::Poor => 'Poor',
            HrvStatus::Low => 'Low',
            HrvStatus::Unbalanced => 'Unbalanced',
            HrvStatus::Balanced => 'Balanced',
            default => 'Unknown',
        };
    }

    private function restingBaseline(User $user, WellnessDay $day): ?int
    {
        $recent = $user->wellnessDays()
            ->whereDate('date', '<', $day->date->toDateString())
            ->orderByDesc('date')
            ->limit(30)
            ->pluck('resting_hr')
            ->filter(fn (mixed $hr): bool => is_numeric($hr) && $hr > 0);

        if ($recent->count() < 3) {
            return null;
        }

        return (int) round((float) $recent->avg());
    }

    private function verdict(WellnessDay $day, ?float $acwr): string
    {
        $levels = [
            $this->fromHrv($day->hrv_status),
            $this->fromBodyBattery($day->body_battery_high),
            $this->fromLoad($acwr),
        ];

        return max($levels) === 2 ? self::REST : (max($levels) === 1 ? self::CAUTION : self::READY);
    }

    private function fromHrv(?HrvStatus $status): int
    {
        return match ($status) {
            HrvStatus::Poor => 2,
            HrvStatus::Low, HrvStatus::Unbalanced => 1,
            default => 0,
        };
    }

    private function fromBodyBattery(?int $high): int
    {
        return $high !== null && $high < 25 ? 1 : 0;
    }

    private function fromLoad(?float $acwr): int
    {
        return match (true) {
            $acwr === null => 0,
            $acwr > 1.5 => 2,
            $acwr > 1.3 => 1,
            default => 0,
        };
    }
}
