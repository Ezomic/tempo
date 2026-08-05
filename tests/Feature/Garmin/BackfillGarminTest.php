<?php

declare(strict_types=1);

use App\Actions\SyncGarminAction;
use App\DataObjects\ActivitySummary;
use App\DataObjects\ConnectionStatus;
use App\DataObjects\LoginResult;
use App\DataObjects\ParsedActivity;
use App\DataObjects\WellnessSnapshot;
use App\Jobs\BackfillGarminJob;
use App\Jobs\RecomputeTrainingCurvesJob;
use App\Models\Activity;
use App\Models\GarminConnection;
use App\Models\HrZoneSettings;
use App\Models\User;
use App\Models\WellnessDay;
use App\Services\Garmin\FitParser;
use App\Services\Garmin\GarminClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

/**
 * Serves one activity per requested window, dated at the window start, and
 * records every range and download it was asked for.
 */
function historyClient(): GarminClient
{
    return new class implements GarminClient
    {
        /** @var list<string> */
        public array $ranges = [];

        /** @var list<string> */
        public array $downloads = [];

        public function activities(GarminConnection $connection, CarbonImmutable $start, CarbonImmutable $end): array
        {
            $this->ranges[] = "{$start->toDateString()}..{$end->toDateString()}";

            return [ActivitySummary::fromSidecar([
                'activityId' => 'act-'.$start->toDateString(),
                'activityType' => ['typeKey' => 'running'],
                'startTimeGMT' => $start->toDateTimeString(),
                'duration' => 3600,
                'distance' => 10000,
                'averageHR' => 150,
            ])];
        }

        public function downloadFit(GarminConnection $connection, string $activityId): string
        {
            $this->downloads[] = $activityId;

            return 'FAKE-FIT-BYTES';
        }

        public function status(GarminConnection $connection): ConnectionStatus
        {
            return new ConnectionStatus(true);
        }

        public function forget(GarminConnection $connection): void {}

        public function login(GarminConnection $connection, string $email, string $password): LoginResult
        {
            return new LoginResult('ok');
        }

        public function resumeLoginWithMfa(GarminConnection $connection, string $loginToken, string $code): LoginResult
        {
            return new LoginResult('ok');
        }

        public function wellness(GarminConnection $connection, CarbonImmutable $date): WellnessSnapshot
        {
            return WellnessSnapshot::fromSidecar([
                'date' => $date->toDateString(),
                'resting_hr' => ['restingHeartRate' => 48],
            ]);
        }

        public function pushWorkout(GarminConnection $connection, array $workout, CarbonImmutable $date): string
        {
            return '1';
        }
    };
}

function backfillParser(): FitParser
{
    return new class extends FitParser
    {
        public function parseData(string $bytes): ParsedActivity
        {
            $samples = [];
            for ($t = 0; $t <= 60; $t++) {
                $samples[1_700_000_000 + $t] = 150;
            }

            return new ParsedActivity($samples);
        }
    };
}

function backfillConnection(): GarminConnection
{
    $user = User::factory()->create();
    HrZoneSettings::create(['user_id' => $user->id, 'max_hr' => 190, 'resting_hr' => 50, 'sex' => 'male']);

    return GarminConnection::create([
        'user_id' => $user->id,
        'status' => GarminConnection::STATUS_CONNECTED,
        // Already synced recently, so the incremental window cannot reach the
        // range this test asks for.
        'last_synced_at' => now(),
    ]);
}

beforeEach(function () {
    Storage::fake('local');
    $this->app->instance(FitParser::class, backfillParser());
});

it('imports activities from a range the incremental window can never reach', function () {
    $client = historyClient();
    $this->app->instance(GarminClient::class, $client);
    $connection = backfillConnection();

    app(SyncGarminAction::class)->backfill(
        $connection,
        CarbonImmutable::parse('2024-01-01'),
        CarbonImmutable::parse('2024-01-05'),
    );

    $activity = Activity::query()->where('user_id', $connection->user_id)->sole();

    expect($client->ranges)->toBe(['2024-01-01..2024-01-05'])
        ->and($activity->external_id)->toBe('act-2024-01-01')
        ->and($activity->trimp)->toBeGreaterThan(0.0)
        ->and($activity->fit_path)->not->toBeNull();
});

it('imports the wellness days in the range too', function () {
    $this->app->instance(GarminClient::class, historyClient());
    $connection = backfillConnection();

    app(SyncGarminAction::class)->backfill(
        $connection,
        CarbonImmutable::parse('2024-01-01'),
        CarbonImmutable::parse('2024-01-05'),
    );

    expect(WellnessDay::query()->where('user_id', $connection->user_id)->count())->toBe(5);
});

it('does not re-download archives when the same range is run again', function () {
    $first = historyClient();
    $this->app->instance(GarminClient::class, $first);
    $connection = backfillConnection();
    $range = [CarbonImmutable::parse('2024-01-01'), CarbonImmutable::parse('2024-01-05')];

    app(SyncGarminAction::class)->backfill($connection, ...$range);
    expect($first->downloads)->toHaveCount(1);

    $second = historyClient();
    $this->app->instance(GarminClient::class, $second);
    app(SyncGarminAction::class)->backfill($connection, ...$range);

    expect($second->downloads)->toBeEmpty()
        ->and(Activity::query()->where('user_id', $connection->user_id)->count())->toBe(1)
        ->and(WellnessDay::query()->where('user_id', $connection->user_id)->count())->toBe(5);
});

it('refuses to backfill a connection that is not connected', function () {
    $this->app->instance(GarminClient::class, historyClient());
    $connection = backfillConnection();
    $connection->forceFill(['status' => GarminConnection::STATUS_DISCONNECTED])->save();

    expect(fn () => app(SyncGarminAction::class)->backfill(
        $connection,
        CarbonImmutable::parse('2024-01-01'),
        CarbonImmutable::parse('2024-01-05'),
    ))->toThrow(RuntimeException::class);
});

it('queues one job per slice with a curve rebuild at the end', function () {
    Bus::fake();
    $connection = backfillConnection();

    $this->artisan('garmin:backfill', [
        '--from' => '2024-01-01',
        '--to' => '2024-03-10',
        '--slice' => 30,
        '--user' => $connection->user_id,
    ])->assertSuccessful();

    Bus::assertChained([
        BackfillGarminJob::class,
        BackfillGarminJob::class,
        BackfillGarminJob::class,
        RecomputeTrainingCurvesJob::class,
    ]);
});

it('rejects a range that runs backwards', function () {
    $connection = backfillConnection();

    $this->artisan('garmin:backfill', [
        '--from' => '2024-03-01',
        '--to' => '2024-01-01',
        '--user' => $connection->user_id,
    ])->assertFailed();
});

it('requires an explicit start date', function () {
    $this->artisan('garmin:backfill')->assertFailed();
});

it('covers the whole range across slices without gaps or overlap', function () {
    Bus::fake();
    $connection = backfillConnection();

    $this->artisan('garmin:backfill', [
        '--from' => '2024-01-01',
        '--to' => '2024-01-25',
        '--slice' => 10,
        '--user' => $connection->user_id,
    ])->assertSuccessful();

    /** @var BackfillGarminJob $head */
    $head = Bus::dispatched(BackfillGarminJob::class)->first();
    $windows = [[$head->start, $head->end]];

    foreach ($head->chained as $serialized) {
        $job = unserialize($serialized);
        if ($job instanceof BackfillGarminJob) {
            $windows[] = [$job->start, $job->end];
        }
    }

    // Contiguous, inclusive, and stopping exactly on --to rather than running
    // past it on the short final slice.
    expect($windows)->toBe([
        ['2024-01-01', '2024-01-10'],
        ['2024-01-11', '2024-01-20'],
        ['2024-01-21', '2024-01-25'],
    ]);
});
