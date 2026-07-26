<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Models\PersonalRecord;
use App\Models\User;
use Carbon\CarbonImmutable;

class PublicProfileService
{
    public function __construct(private readonly FitnessCurveService $fitnessCurve) {}

    /**
     * The whitelisted, read-only view of an athlete: display name, current
     * form, recent personal bests, and a fitness sparkline. No wellness, no
     * raw activities, nothing not listed here.
     *
     * @return array{name: string, form: float|null, ctl: float|null, records: list<array{distance_m: int, time: string, achieved_on: string}>, sparkline: list<float>}
     */
    public function publicData(User $user): array
    {
        $today = CarbonImmutable::now();
        $series = $this->fitnessCurve->series($user, $today->subDays(90), $today);
        $latest = $series === [] ? null : $series[array_key_last($series)];

        return [
            'name' => $user->name,
            'form' => $latest['tsb'] ?? null,
            'ctl' => $latest['ctl'] ?? null,
            'records' => $this->records($user),
            'sparkline' => array_map(fn (array $p): float => $p['ctl'], $series),
        ];
    }

    /**
     * @return list<array{distance_m: int, time: string, achieved_on: string}>
     */
    private function records(User $user): array
    {
        return array_values($user->personalRecords()
            ->orderBy('distance_m')
            ->get(['distance_m', 'duration_s', 'achieved_on'])
            ->map(fn (PersonalRecord $record): array => [
                'distance_m' => $record->distance_m,
                'time' => $this->formatTime($record->duration_s),
                'achieved_on' => $record->achieved_on->toDateString(),
            ])
            ->all());
    }

    private function formatTime(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return $h > 0
            ? sprintf('%d:%02d:%02d', $h, $m, $s)
            : sprintf('%d:%02d', $m, $s);
    }
}
