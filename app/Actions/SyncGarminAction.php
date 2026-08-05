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
use App\Services\Garmin\ActivityMetrics;
use App\Services\Garmin\FitParser;
use App\Services\Garmin\GarminClient;
use App\Services\Garmin\StreamBuilder;
use App\Services\Training\FitnessCurveService;
use App\Services\Training\PerformanceRecordService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    /**
     * How many previously failed archives one run tries to recover, so a long
     * backlog does not push the sync past its timeout.
     */
    private const ARCHIVE_REPAIRS_PER_RUN = 25;

    public function __construct(
        private readonly GarminClient $client,
        private readonly FitParser $fitParser,
        private readonly StreamBuilder $streamBuilder,
        private readonly ActivityMetrics $metrics,
        private readonly FitnessCurveService $fitnessCurve,
        private readonly PerformanceRecordService $records,
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

            // Activities whose archive failed earlier fall outside the window
            // above forever, so they get their own pass.
            $this->repairMissingArchives($connection, $settings);

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

        $attributes += $this->archiveAndDerive(
            $connection,
            $summary->externalId,
            $summary->sport,
            $summary->distanceM,
            $settings,
        );

        Activity::query()->updateOrCreate(
            ['user_id' => $connection->user_id, 'external_id' => $summary->externalId],
            $attributes,
        );
    }

    /**
     * Re-download the archives that failed on an earlier run. Without this the
     * activity keeps a null trimp forever: it is outside every later sync window,
     * and ReprocessActivitiesAction only reads archives that are already on disk.
     */
    private function repairMissingArchives(GarminConnection $connection, HrZoneSettings $settings): void
    {
        $broken = Activity::query()
            ->where('user_id', $connection->user_id)
            ->whereNull('fit_path')
            ->whereNotNull('fit_failed_at')
            ->orderByDesc('started_at')
            ->limit(self::ARCHIVE_REPAIRS_PER_RUN)
            ->get();

        foreach ($broken as $activity) {
            $activity->forceFill($this->archiveAndDerive(
                $connection,
                $activity->external_id,
                $activity->sport,
                $activity->distance_m,
                $settings,
            ))->save();
        }
    }

    /**
     * Download and archive the .fit, then derive everything it yields. Returns
     * the attributes to write, including the outcome of the download itself so
     * a failure is recorded rather than silently dropped.
     *
     * @return array<string, mixed>
     */
    private function archiveAndDerive(
        GarminConnection $connection,
        string $externalId,
        Sport $sport,
        ?float $distanceM,
        HrZoneSettings $settings,
    ): array {
        try {
            $bytes = $this->client->downloadFit($connection, $externalId);
        } catch (Throwable $e) {
            return $this->archiveFailure($connection, $externalId, $e->getMessage());
        }

        if ($bytes === '') {
            return $this->archiveFailure($connection, $externalId, 'Garmin returned an empty .fit file.');
        }

        $path = "garmin/fit/{$connection->user_id}/{$externalId}.fit";
        Storage::disk('local')->put($path, $bytes);

        $parsed = $this->fitParser->parseData($bytes);

        $attributes = $this->metrics->derive($parsed, $sport, $distanceM, $settings) + [
            'fit_path' => $path,
            'fit_failed_at' => null,
            'fit_error' => null,
        ];

        $streamsPath = $this->archiveStreams($connection, $externalId, $parsed);
        if ($streamsPath !== null) {
            $attributes['streams_path'] = $streamsPath;
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function archiveFailure(GarminConnection $connection, string $externalId, string $reason): array
    {
        Log::warning('Garmin .fit download failed', [
            'user_id' => $connection->user_id,
            'external_id' => $externalId,
            'reason' => $reason,
        ]);

        return ['fit_failed_at' => now(), 'fit_error' => Str::limit($reason, 250)];
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
