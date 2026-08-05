<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataObjects\ActivitySummary;
use App\DataObjects\ParsedActivity;
use App\Enums\Sport;
use App\Models\Activity;
use App\Models\GarminConnection;
use App\Models\HrZoneSettings;
use App\Models\WellnessDay;
use App\Services\Garmin\FitParser;
use App\Services\Garmin\GarminClient;
use App\Services\Garmin\StreamBuilder;
use App\Services\Garmin\TrimpCalculator;
use App\Services\Routing\RouteSignature;
use App\Services\Training\AerobicDecouplingAnalyzer;
use App\Services\Training\CardiacCostAnalyzer;
use App\Services\Training\EfficiencyFactorAnalyzer;
use App\Services\Training\EffortAnalyzer;
use App\Services\Training\FitnessCurveService;
use App\Services\Training\PerformanceRecordService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SyncGarminAction
{
    private const FIRST_SYNC_ACTIVITY_DAYS = 90;

    private const FIRST_SYNC_WELLNESS_DAYS = 30;

    /**
     * Every incremental run reaches this far back regardless of last_synced_at.
     * Days already captured are skipped without a call, so the only cost is a
     * cheap query, and it is what lets a day that failed transiently be picked
     * up by a later run instead of being lost.
     */
    private const WELLNESS_LOOKBACK_DAYS = 14;

    public function __construct(
        private readonly GarminClient $client,
        private readonly FitParser $fitParser,
        private readonly TrimpCalculator $trimp,
        private readonly StreamBuilder $streamBuilder,
        private readonly EffortAnalyzer $effort,
        private readonly FitnessCurveService $fitnessCurve,
        private readonly PerformanceRecordService $records,
        private readonly AerobicDecouplingAnalyzer $decoupling,
        private readonly EfficiencyFactorAnalyzer $efficiency,
        private readonly CardiacCostAnalyzer $cardiac,
        private readonly RouteSignature $routeSignature,
    ) {}

    public function handle(GarminConnection $connection): void
    {
        if (! $connection->isConnected()) {
            throw new RuntimeException('Garmin connection is not connected.');
        }

        $connection->update([
            'sync_status' => GarminConnection::SYNC_SYNCING,
            'sync_status_since' => now(),
            'sync_error' => null,
        ]);

        try {
            $now = CarbonImmutable::now();
            $lastSynced = $connection->last_synced_at !== null
                ? CarbonImmutable::parse($connection->last_synced_at)
                : null;

            $settings = $this->settingsFor($connection);

            $activityStart = $lastSynced?->subDay() ?? $now->subDays(self::FIRST_SYNC_ACTIVITY_DAYS);
            foreach ($this->client->activities($connection, $activityStart, $now) as $summary) {
                $this->storeActivity($connection, $summary, $settings);
            }

            $wellnessStart = $lastSynced !== null
                ? $lastSynced->subDay()->min($now->subDays(self::WELLNESS_LOOKBACK_DAYS))
                : $now->subDays(self::FIRST_SYNC_WELLNESS_DAYS);
            $this->syncWellness($connection, $wellnessStart->startOfDay(), $now->startOfDay());

            $this->fitnessCurve->recompute($connection->user, $now);
            $this->records->recompute($connection->user);

            $connection->update([
                'sync_status' => GarminConnection::SYNC_IDLE,
                'sync_status_since' => now(),
                'last_synced_at' => now(),
            ]);
        } catch (Throwable $e) {
            $connection->update([
                'sync_status' => GarminConnection::SYNC_ERROR,
                'sync_status_since' => now(),
                'sync_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function storeActivity(GarminConnection $connection, ActivitySummary $summary, HrZoneSettings $settings): void
    {
        if ($summary->externalId === '') {
            return;
        }

        $attributes = [
            'sport' => $summary->sport,
            'sub_sport' => $summary->subSport,
            'started_at' => $summary->startedAt,
            'timezone' => $summary->timezone,
            'duration_s' => $summary->durationS,
            'moving_time_s' => $summary->movingTimeS,
            'distance_m' => $summary->distanceM,
            'avg_hr' => $summary->avgHr,
            'max_hr' => $summary->maxHr,
            'elevation_gain_m' => $summary->elevationGainM,
            'avg_speed_mps' => $summary->avgSpeedMps,
            'calories' => $summary->calories,
            'raw_summary' => $summary->raw,
        ];

        $fit = $this->archiveFit($connection, $summary->externalId);
        if ($fit !== null) {
            $parsed = $this->fitParser->parseData($fit['bytes']);
            $attributes['fit_path'] = $fit['path'];
            if ($parsed->hasHeartRate()) {
                $attributes['trimp'] = $this->trimp->trimp($parsed->hrSamples, $settings);
                $attributes['hr_zone_seconds'] = $this->trimp->zoneSeconds($parsed->hrSamples, $settings);
            }

            if ($parsed->speedSamples !== []) {
                if ($summary->sport === Sport::Run) {
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
                $attributes['route_key'] = $this->routeSignature->forPositions($parsed->positions, $summary->distanceM);
            }

            $streamsPath = $this->archiveStreams($connection, $summary->externalId, $parsed);
            if ($streamsPath !== null) {
                $attributes['streams_path'] = $streamsPath;
            }
        }

        Activity::query()->updateOrCreate(
            ['user_id' => $connection->user_id, 'external_id' => $summary->externalId],
            $attributes,
        );
    }

    /**
     * @return array{bytes: string, path: string}|null
     */
    private function archiveFit(GarminConnection $connection, string $externalId): ?array
    {
        try {
            $bytes = $this->client->downloadFit($connection, $externalId);
        } catch (Throwable) {
            return null;
        }

        if ($bytes === '') {
            return null;
        }

        $path = "garmin/fit/{$connection->user_id}/{$externalId}.fit";
        Storage::disk('local')->put($path, $bytes);

        return ['bytes' => $bytes, 'path' => $path];
    }

    private function archiveStreams(GarminConnection $connection, string $externalId, ParsedActivity $parsed): ?string
    {
        if ($parsed->hrSamples === [] && $parsed->speedSamples === [] && $parsed->positions === []) {
            return null;
        }

        $path = "garmin/streams/{$connection->user_id}/{$externalId}.json";
        Storage::disk('local')->put($path, (string) json_encode($this->streamBuilder->build($parsed)));

        return $path;
    }

    private function syncWellness(GarminConnection $connection, CarbonImmutable $start, CarbonImmutable $today): void
    {
        $attempted = 0;
        $skipped = [];

        for ($date = $start; $date <= $today; $date = $date->addDay()) {
            // whereDate compares only the date part, so it matches the stored
            // datetime regardless of its time component; a bare-string equality
            // match on the cast column silently misses and duplicates the row.
            $existing = WellnessDay::query()
                ->where('user_id', $connection->user_id)
                ->whereDate('date', $date->toDateString())
                ->first();

            // Past days are immutable once captured; only re-fetch today.
            if ($existing !== null && $date->lessThan($today)) {
                continue;
            }

            $attempted++;

            try {
                $this->storeWellnessDay($connection, $existing, $date);
            } catch (Throwable $e) {
                // Rate limiting and a briefly unreachable Garmin are exactly the
                // failures that must not cost the whole run. The lookback window
                // brings this date back around on a later sync.
                $skipped[] = $date->toDateString();
                Log::warning('Wellness day skipped during Garmin sync', [
                    'user_id' => $connection->user_id,
                    'date' => $date->toDateString(),
                    'reason' => $e->getMessage(),
                ]);
            }
        }

        // Every single day failing is not a transient blip, it is the sidecar or
        // the session being down, and that has to reach the athlete.
        if ($attempted > 0 && count($skipped) === $attempted) {
            throw new RuntimeException('Could not fetch any wellness data from Garmin.');
        }
    }

    private function storeWellnessDay(GarminConnection $connection, ?WellnessDay $existing, CarbonImmutable $date): void
    {
        $snapshot = $this->client->wellness($connection, $date);

        ($existing ?? new WellnessDay)->forceFill([
            'user_id' => $connection->user_id,
            'date' => $date->toDateString(),
            'sleep_score' => $snapshot->sleepScore,
            'sleep_duration_s' => $snapshot->sleepDurationS,
            'hrv_status' => $snapshot->hrvStatus,
            'hrv_last_night_ms' => $snapshot->hrvLastNightMs,
            'hrv_baseline_low' => $snapshot->hrvBaselineLow,
            'hrv_baseline_high' => $snapshot->hrvBaselineHigh,
            'body_battery_high' => $snapshot->bodyBatteryHigh,
            'body_battery_low' => $snapshot->bodyBatteryLow,
            'resting_hr' => $snapshot->restingHr,
            'stress_avg' => $snapshot->stressAvg,
            'raw' => $snapshot->raw,
        ])->save();
    }

    private function settingsFor(GarminConnection $connection): HrZoneSettings
    {
        return HrZoneSettings::query()->firstOrNew(['user_id' => $connection->user_id]);
    }
}
