<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Support\Payload;

class EffortAnalyzer
{
    /**
     * Fastest time to cover each configured distance, from the speed stream.
     *
     * @param  array<int, float>  $speedSamples  m/s keyed by unix timestamp
     * @return array<int, int> distance_m => seconds
     */
    public function bestEfforts(array $speedSamples): array
    {
        $series = $this->cumulative($speedSamples);
        $t = $series['t'];
        $d = $series['d'];
        $n = count($t);

        $records = [];
        foreach ($this->distances() as $target) {
            $best = null;
            $j = 0;
            for ($i = 0; $i < $n; $i++) {
                if ($j < $i) {
                    $j = $i;
                }
                while ($j < $n && $target > $d[$j] - $d[$i]) {
                    $j++;
                }
                if ($j >= $n) {
                    break; // no further window can reach the target
                }
                $elapsed = $t[$j] - $t[$i];
                if ($elapsed > 0 && ($best === null || $elapsed < $best)) {
                    $best = $elapsed;
                }
            }

            if ($best !== null) {
                $records[$target] = $best;
            }
        }

        return $records;
    }

    /**
     * Best average speed sustained for each configured duration.
     *
     * @param  array<int, float>  $speedSamples  m/s keyed by unix timestamp
     * @return array<int, float> duration_s => m/s
     */
    public function meanMax(array $speedSamples): array
    {
        $series = $this->cumulative($speedSamples);
        $t = $series['t'];
        $d = $series['d'];
        $n = count($t);

        $records = [];
        foreach ($this->durations() as $window) {
            $best = null;
            $j = 0;
            for ($i = 0; $i < $n; $i++) {
                if ($j < $i) {
                    $j = $i;
                }
                while ($j < $n && $window > $t[$j] - $t[$i]) {
                    $j++;
                }
                if ($j >= $n) {
                    break;
                }
                $elapsed = $t[$j] - $t[$i];
                if ($elapsed > 0) {
                    $speed = ($d[$j] - $d[$i]) / $elapsed;
                    if ($best === null || $speed > $best) {
                        $best = $speed;
                    }
                }
            }

            if ($best !== null) {
                $records[$window] = round($best, 3);
            }
        }

        return $records;
    }

    /**
     * @param  array<int, float>  $speedSamples
     * @return array{t: list<int>, d: list<float>}
     */
    private function cumulative(array $speedSamples): array
    {
        ksort($speedSamples);

        $t = [];
        $d = [];
        $distance = 0.0;
        $previousTs = null;
        foreach ($speedSamples as $ts => $speed) {
            if ($previousTs !== null) {
                $dt = $ts - $previousTs;
                // Skip gaps (pauses) that would fabricate distance.
                if ($dt > 0 && $dt <= 10) {
                    $distance += $speed * $dt;
                }
            }
            $t[] = $ts;
            $d[] = $distance;
            $previousTs = $ts;
        }

        return ['t' => $t, 'd' => $d];
    }

    /**
     * @return list<int>
     */
    private function distances(): array
    {
        return $this->intList(config('training.records.distances_m'));
    }

    /**
     * @return list<int>
     */
    private function durations(): array
    {
        return $this->intList(config('training.records.durations_s'));
    }

    /**
     * @return list<int>
     */
    private function intList(mixed $value): array
    {
        return is_array($value)
            ? array_values(array_map(static fn (mixed $item): int => Payload::toInt($item), $value))
            : [];
    }
}
