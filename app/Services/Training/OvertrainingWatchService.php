<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Models\User;
use Carbon\CarbonImmutable;

class OvertrainingWatchService
{
    /**
     * Compare recent wellness to a baseline and raise a graded warning when
     * resting HR is elevated, HRV suppressed, or sleep short. Returns null
     * when nothing is tripped.
     *
     * @return array{level: string, reasons: list<string>}|null
     */
    public function watch(User $user, CarbonImmutable $today): ?array
    {
        $recentDays = (int) config('training.overtraining.recent_days');
        $baselineDays = (int) config('training.overtraining.baseline_days');

        $recent = $this->window($user, $today->subDays($recentDays - 1), $today);
        $baseline = $this->window($user, $today->subDays($recentDays + $baselineDays - 1), $today->subDays($recentDays));

        $reasons = [];

        $rhr = $this->restingHrReason($recent, $baseline);
        if ($rhr !== null) {
            $reasons[] = $rhr;
        }

        $hrv = $this->hrvReason($recent, $baseline);
        if ($hrv !== null) {
            $reasons[] = $hrv;
        }

        $sleep = $this->sleepReason($recent);
        if ($sleep !== null) {
            $reasons[] = $sleep;
        }

        if ($reasons === []) {
            return null;
        }

        return [
            'level' => count($reasons) >= 2 ? 'back_off' : 'watch',
            'reasons' => $reasons,
        ];
    }

    /**
     * @param  array{rhr: float|null, hrv: float|null, sleep: float|null}  $recent
     * @param  array{rhr: float|null, hrv: float|null, sleep: float|null}  $baseline
     */
    private function restingHrReason(array $recent, array $baseline): ?string
    {
        if ($recent['rhr'] === null || $baseline['rhr'] === null) {
            return null;
        }

        $delta = $recent['rhr'] - $baseline['rhr'];
        if ($delta >= (float) config('training.overtraining.rhr_elevation_bpm')) {
            return 'Resting HR is up '.round($delta, 1).' bpm on your baseline.';
        }

        return null;
    }

    /**
     * @param  array{rhr: float|null, hrv: float|null, sleep: float|null}  $recent
     * @param  array{rhr: float|null, hrv: float|null, sleep: float|null}  $baseline
     */
    private function hrvReason(array $recent, array $baseline): ?string
    {
        if ($recent['hrv'] === null || $baseline['hrv'] === null || $baseline['hrv'] <= 0.0) {
            return null;
        }

        $dropPct = (($baseline['hrv'] - $recent['hrv']) / $baseline['hrv']) * 100;
        if ($dropPct >= (float) config('training.overtraining.hrv_drop_pct')) {
            return 'HRV is down '.round($dropPct).'% on your baseline.';
        }

        return null;
    }

    /**
     * @param  array{rhr: float|null, hrv: float|null, sleep: float|null}  $recent
     */
    private function sleepReason(array $recent): ?string
    {
        $min = (float) config('training.overtraining.sleep_min_hours');
        if ($recent['sleep'] !== null && $recent['sleep'] < $min) {
            return 'Averaging '.round($recent['sleep'], 1).' h of sleep, under '.$min.' h.';
        }

        return null;
    }

    /**
     * @return array{rhr: float|null, hrv: float|null, sleep: float|null}
     */
    private function window(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = $user->wellnessDays()
            ->whereBetween('date', [$from->toDateString().' 00:00:00', $to->toDateString().' 23:59:59'])
            ->get(['resting_hr', 'hrv_last_night_ms', 'sleep_duration_s']);

        return [
            'rhr' => $this->average($days->pluck('resting_hr')->all()),
            'hrv' => $this->average($days->pluck('hrv_last_night_ms')->all()),
            'sleep' => $this->average($days->pluck('sleep_duration_s')->map(
                fn (?int $s): ?float => $s === null ? null : $s / 3600
            )->all()),
        ];
    }

    /**
     * @param  array<int, int|float|null>  $values
     */
    private function average(array $values): ?float
    {
        $present = array_values(array_filter($values, fn ($v): bool => $v !== null));

        return $present === [] ? null : array_sum($present) / count($present);
    }
}
