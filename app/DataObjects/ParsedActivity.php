<?php

declare(strict_types=1);

namespace App\DataObjects;

final readonly class ParsedActivity
{
    /**
     * Heart-rate samples keyed by unix timestamp (as recorded in the FIT file).
     *
     * @param  array<int, int>  $hrSamples
     */
    public function __construct(
        public array $hrSamples,
    ) {}

    public function hasHeartRate(): bool
    {
        return $this->hrSamples !== [];
    }
}
