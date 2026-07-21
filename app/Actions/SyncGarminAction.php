<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataObjects\ActivitySummary;
use App\Models\Activity;
use App\Models\GarminConnection;
use App\Models\HrZoneSettings;
use App\Models\WellnessDay;
use App\Services\Garmin\FitParser;
use App\Services\Garmin\GarminClient;
use App\Services\Garmin\TrimpCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SyncGarminAction
{
    private const FIRST_SYNC_ACTIVITY_DAYS = 90;

    private const FIRST_SYNC_WELLNESS_DAYS = 30;

    public function __construct(
        private readonly GarminClient $client,
        private readonly FitParser $fitParser,
        private readonly TrimpCalculator $trimp,
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

            $wellnessStart = $lastSynced?->subDay() ?? $now->subDays(self::FIRST_SYNC_WELLNESS_DAYS);
            $this->syncWellness($connection, $wellnessStart->startOfDay(), $now->startOfDay());

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

    private function syncWellness(GarminConnection $connection, CarbonImmutable $start, CarbonImmutable $today): void
    {
        for ($date = $start; $date <= $today; $date = $date->addDay()) {
            $alreadyStored = WellnessDay::query()
                ->where('user_id', $connection->user_id)
                ->whereDate('date', $date->toDateString())
                ->exists();

            // Past days are immutable once captured; only re-fetch today.
            if ($alreadyStored && $date->lessThan($today)) {
                continue;
            }

            $snapshot = $this->client->wellness($connection, $date);

            WellnessDay::query()->updateOrCreate(
                ['user_id' => $connection->user_id, 'date' => $date->toDateString()],
                [
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
                ],
            );
        }
    }

    private function settingsFor(GarminConnection $connection): HrZoneSettings
    {
        return HrZoneSettings::query()->firstOrNew(['user_id' => $connection->user_id]);
    }
}
