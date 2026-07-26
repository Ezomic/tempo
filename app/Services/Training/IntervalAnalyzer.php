<?php

declare(strict_types=1);

namespace App\Services\Training;

class IntervalAnalyzer
{
    private const MIN_LAPS = 3;

    private const STRUCTURE_COV = 0.08;

    private const HIT = 0.98;

    private const SLIGHTLY_OFF = 0.94;

    /**
     * Classify laps into work and recovery and, for a structured session,
     * score each work rep against the session's own work pace. Deterministic
     * and computed purely from the stored laps.
     *
     * @param  list<array{duration_s: int, distance_m: float, avg_speed_mps: float, avg_hr: int|null}>  $laps
     * @return array{structured: bool, target_speed_mps: float|null, counts: array{work: int, hit: int, slightly_off: int, missed: int}, intervals: list<array{type: string, duration_s: int, distance_m: float, avg_speed_mps: float, avg_hr: int|null, verdict: string|null}>}
     */
    public function analyze(array $laps): array
    {
        $speeds = array_map(fn (array $lap): float => $lap['avg_speed_mps'], $laps);
        $structured = $this->isStructured($speeds);

        $threshold = $speeds === [] ? 0.0 : array_sum($speeds) / count($speeds);
        $workSpeeds = array_values(array_filter($speeds, fn (float $s): bool => $s >= $threshold));
        $target = $structured && $workSpeeds !== []
            ? array_sum($workSpeeds) / count($workSpeeds)
            : null;

        $counts = ['work' => 0, 'hit' => 0, 'slightly_off' => 0, 'missed' => 0];
        $intervals = [];
        foreach ($laps as $lap) {
            $isWork = $lap['avg_speed_mps'] >= $threshold;
            $verdict = null;

            if ($structured && $isWork) {
                $counts['work']++;
                $verdict = $this->verdict($lap['avg_speed_mps'], $target);
                $counts[$verdict]++;
            }

            $intervals[] = [
                'type' => $isWork ? 'work' : 'recovery',
                'duration_s' => $lap['duration_s'],
                'distance_m' => $lap['distance_m'],
                'avg_speed_mps' => $lap['avg_speed_mps'],
                'avg_hr' => $lap['avg_hr'],
                'verdict' => $verdict,
            ];
        }

        return [
            'structured' => $structured,
            'target_speed_mps' => $target !== null ? round($target, 3) : null,
            'counts' => [
                'work' => $counts['work'],
                'hit' => $counts['hit'],
                'slightly_off' => $counts['slightly_off'],
                'missed' => $counts['missed'],
            ],
            'intervals' => $intervals,
        ];
    }

    /**
     * @param  list<float>  $speeds
     */
    private function isStructured(array $speeds): bool
    {
        $n = count($speeds);
        if ($n < self::MIN_LAPS) {
            return false;
        }

        $mean = array_sum($speeds) / $n;
        if ($mean <= 0) {
            return false;
        }

        $variance = array_sum(array_map(fn (float $s): float => ($s - $mean) ** 2, $speeds)) / $n;
        $cov = sqrt($variance) / $mean;

        $workCount = count(array_filter($speeds, fn (float $s): bool => $s >= $mean));

        return $cov >= self::STRUCTURE_COV && $workCount >= 2;
    }

    private function verdict(float $speed, ?float $target): string
    {
        if ($target === null || $target <= 0) {
            return 'hit';
        }

        $ratio = $speed / $target;

        return match (true) {
            $ratio >= self::HIT => 'hit',
            $ratio >= self::SLIGHTLY_OFF => 'slightly_off',
            default => 'missed',
        };
    }
}
