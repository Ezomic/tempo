<?php

declare(strict_types=1);

namespace App\Services\Garmin;

use App\DataObjects\ParsedActivity;
use App\Enums\Sport;
use App\Models\HrZoneSettings;
use App\Services\Routing\RouteSignature;
use App\Services\Training\AerobicDecouplingAnalyzer;
use App\Services\Training\CardiacCostAnalyzer;
use App\Services\Training\EfficiencyFactorAnalyzer;
use App\Services\Training\EffortAnalyzer;

/**
 * The single definition of what a parsed .fit yields. A first sync, a repair of
 * a failed download and a reprocess run all have to land on the same numbers,
 * so they all derive them here.
 */
class ActivityMetrics
{
    public function __construct(
        private readonly TrimpCalculator $trimp,
        private readonly EffortAnalyzer $effort,
        private readonly AerobicDecouplingAnalyzer $decoupling,
        private readonly EfficiencyFactorAnalyzer $efficiency,
        private readonly CardiacCostAnalyzer $cardiac,
        private readonly RouteSignature $routeSignature,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function derive(ParsedActivity $parsed, Sport $sport, ?float $distanceM, HrZoneSettings $settings): array
    {
        $attributes = [];

        if ($parsed->hasHeartRate()) {
            $attributes['trimp'] = $this->trimp->trimp($parsed->hrSamples, $settings);
            $attributes['hr_zone_seconds'] = $this->trimp->zoneSeconds($parsed->hrSamples, $settings);
        }

        if ($parsed->speedSamples !== []) {
            if ($sport === Sport::Run) {
                $attributes['best_efforts'] = $this->effort->bestEfforts($parsed->speedSamples);
            }
            $attributes['mean_max'] = $this->effort->meanMax($parsed->speedSamples);
        }

        if ($parsed->laps !== []) {
            $attributes['laps'] = $parsed->laps;
        }

        if ($parsed->hasHeartRate() && $parsed->speedSamples !== []) {
            $attributes['decoupling'] = $this->decoupling->analyze($parsed->hrSamples, $parsed->speedSamples);
            $attributes['efficiency_factor'] = $this->efficiency->analyze($parsed->hrSamples, $parsed->speedSamples);
            $cardiac = $this->cardiac->analyze($parsed->hrSamples, $parsed->speedSamples);
            $attributes['cardiac_cost'] = $cardiac['cardiac_cost'] ?? null;
            $attributes['hr_drift'] = $cardiac['hr_drift'] ?? null;
        }

        if ($parsed->positions !== []) {
            $attributes['route_key'] = $this->routeSignature->forPositions($parsed->positions, $distanceM);
        }

        return $attributes;
    }
}
