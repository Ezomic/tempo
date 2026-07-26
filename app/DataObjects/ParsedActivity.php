<?php

declare(strict_types=1);

namespace App\DataObjects;

final readonly class ParsedActivity
{
    /**
     * @param  array<int, int>  $hrSamples  bpm keyed by unix timestamp
     * @param  array<int, float>  $speedSamples  m/s keyed by unix timestamp
     * @param  array<int, array{0: float, 1: float}>  $positions  [lat, lng] keyed by unix timestamp
     * @param  list<array{duration_s: int, distance_m: float, avg_speed_mps: float, avg_hr: int|null}>  $laps
     */
    public function __construct(
        public array $hrSamples,
        public array $speedSamples = [],
        public array $positions = [],
        public array $laps = [],
    ) {}

    public function hasHeartRate(): bool
    {
        return $this->hrSamples !== [];
    }
}
