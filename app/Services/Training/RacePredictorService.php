<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Enums\Sport;
use App\Models\MeanMaxEffort;
use App\Models\User;

class RacePredictorService
{
    /**
     * Predicted race finishes for the configured distances, derived from the
     * athlete's run mean-max curve and scaled by current fitness.
     *
     * @return list<array{distance_m: int, label: string, seconds: int, source: string}>
     */
    public function predictions(User $user): array
    {
        $meanMax = $user->meanMaxEfforts()
            ->where('sport', Sport::Run)
            ->get(['duration_s', 'speed_mps'])
            ->mapWithKeys(fn (MeanMaxEffort $e): array => [$e->duration_s => $e->speed_mps])
            ->all();

        $currentCtl = (float) ($user->dailyLoadMetrics()->orderByDesc('date')->value('ctl') ?? 0.0);

        return $this->predict($meanMax, $currentCtl);
    }

    /**
     * Predict finish times from a mean-max curve (duration_s => m/s).
     *
     * A reference point is (speed * duration) metres covered in duration
     * seconds; the closest reference on a log-distance scale anchors a Riegel
     * extrapolation to each target. When a projected CTL is supplied, the
     * predicted speed is nudged by the fitness ratio so the forecast tracks
     * where fitness is heading.
     *
     * @param  array<int, float>  $meanMaxBySeconds  duration_s => m/s
     * @return list<array{distance_m: int, label: string, seconds: int, source: string}>
     */
    public function predict(array $meanMaxBySeconds, float $currentCtl, ?float $projectedCtl = null): array
    {
        $references = [];
        foreach ($meanMaxBySeconds as $duration => $speed) {
            $duration = (int) $duration;
            $speed = (float) $speed;
            if ($duration <= 0 || $speed <= 0.0) {
                continue;
            }
            $references[] = ['distance' => $speed * $duration, 'time' => $duration];
        }

        if ($references === []) {
            return [];
        }

        $factor = $this->fitnessFactor($currentCtl, $projectedCtl);
        $exponent = (float) config('training.predictor.riegel_exponent');

        $predictions = [];
        foreach ($this->distances() as $target) {
            $reference = $this->closestReference($references, $target);
            $time = $reference['time'] * ($target / $reference['distance']) ** $exponent;
            $time /= $factor;

            $measured = abs($reference['distance'] - $target) / $target <= 0.05;

            $predictions[] = [
                'distance_m' => $target,
                'label' => $this->label($target),
                'seconds' => (int) round($time),
                'source' => $measured ? 'measured' : 'modelled',
            ];
        }

        return $predictions;
    }

    /**
     * @param  list<array{distance: float, time: int}>  $references
     * @return array{distance: float, time: int}
     */
    private function closestReference(array $references, int $target): array
    {
        $best = $references[0];
        $bestGap = INF;
        foreach ($references as $reference) {
            $gap = abs(log($reference['distance']) - log($target));
            if ($gap < $bestGap) {
                $bestGap = $gap;
                $best = $reference;
            }
        }

        return $best;
    }

    private function fitnessFactor(float $currentCtl, ?float $projectedCtl): float
    {
        if ($projectedCtl === null || $currentCtl <= 0.0 || $projectedCtl <= 0.0) {
            return 1.0;
        }

        return ($projectedCtl / $currentCtl) ** (float) config('training.predictor.fitness_exponent');
    }

    private function label(int $distanceM): string
    {
        return match ($distanceM) {
            5000 => '5K',
            10000 => '10K',
            21097 => 'Half',
            42195 => 'Marathon',
            default => number_format($distanceM / 1000, 1).'K',
        };
    }

    /**
     * @return list<int>
     */
    private function distances(): array
    {
        $value = config('training.predictor.distances_m');

        return is_array($value) ? array_values(array_map(intval(...), $value)) : [];
    }
}
