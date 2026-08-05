<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataObjects\ParsedActivity;
use App\DataObjects\ReprocessResult;
use App\Models\Activity;
use App\Models\HrZoneSettings;
use App\Models\User;
use App\Services\Garmin\ActivityMetrics;
use App\Services\Garmin\FitParser;
use App\Services\Garmin\StreamBuilder;
use App\Services\Training\FitnessCurveService;
use App\Services\Training\PerformanceRecordService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ReprocessActivitiesAction
{
    public function __construct(
        private readonly FitParser $fitParser,
        private readonly StreamBuilder $streamBuilder,
        private readonly ActivityMetrics $metrics,
        private readonly FitnessCurveService $fitnessCurve,
        private readonly PerformanceRecordService $records,
    ) {}

    /**
     * Re-parse each activity's archived .fit file and recompute its derived
     * metrics in place. Reads only from the on-disk archive; never calls the
     * sidecar or Garmin. A parse failure is recorded and the run continues.
     *
     * @param  iterable<Activity>  $activities
     * @param  (Closure(Activity, string): void)|null  $onEach  invoked per activity with the outcome
     */
    public function handle(iterable $activities, ?Closure $onEach = null): ReprocessResult
    {
        $processed = 0;
        $updated = 0;
        $skipped = 0;
        $failures = [];

        /** @var array<int, HrZoneSettings> $settingsCache */
        $settingsCache = [];
        /** @var array<int, true> $affectedUsers */
        $affectedUsers = [];

        foreach ($activities as $activity) {
            $processed++;

            if ($activity->fit_path === null || ! Storage::disk('local')->exists($activity->fit_path)) {
                $skipped++;
                $this->notify($onEach, $activity, 'skipped');

                continue;
            }

            try {
                $bytes = (string) Storage::disk('local')->get($activity->fit_path);
                $parsed = $this->fitParser->parseData($bytes);
                $settings = $settingsCache[$activity->user_id] ??= HrZoneSettings::query()
                    ->firstOrNew(['user_id' => $activity->user_id]);

                $activity->forceFill($this->derive($activity, $parsed, $settings))->save();

                $affectedUsers[$activity->user_id] = true;
                $updated++;
                $this->notify($onEach, $activity, 'updated');
            } catch (Throwable $e) {
                $failures[] = ['id' => $activity->id, 'reason' => $e->getMessage()];
                $this->notify($onEach, $activity, 'failed');
            }
        }

        $this->recomputeCurves(array_keys($affectedUsers));

        return new ReprocessResult($processed, $updated, $skipped, $failures);
    }

    /**
     * @return array<string, mixed>
     */
    private function derive(Activity $activity, ParsedActivity $parsed, HrZoneSettings $settings): array
    {
        $attributes = $this->metrics->derive($parsed, $activity->sport, $activity->distance_m, $settings);

        $streamsPath = "garmin/streams/{$activity->user_id}/{$activity->external_id}.json";
        Storage::disk('local')->put($streamsPath, (string) json_encode($this->streamBuilder->build($parsed)));
        $attributes['streams_path'] = $streamsPath;

        return $attributes;
    }

    /**
     * @param  list<int>  $userIds
     */
    private function recomputeCurves(array $userIds): void
    {
        $today = CarbonImmutable::now();

        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if ($user !== null) {
                $this->fitnessCurve->recompute($user, $today);
                $this->records->recompute($user);
            }
        }
    }

    /**
     * @param  (Closure(Activity, string): void)|null  $onEach
     */
    private function notify(?Closure $onEach, Activity $activity, string $outcome): void
    {
        if ($onEach !== null) {
            $onEach($activity, $outcome);
        }
    }
}
