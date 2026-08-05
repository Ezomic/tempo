<?php

declare(strict_types=1);

use App\Actions\SyncGarminAction;
use App\DataObjects\ActivitySummary;
use App\DataObjects\ConnectionStatus;
use App\DataObjects\LoginResult;
use App\DataObjects\ParsedActivity;
use App\DataObjects\WellnessSnapshot;
use App\Models\Activity;
use App\Models\GarminConnection;
use App\Models\HrZoneSettings;
use App\Models\User;
use App\Services\Garmin\FitParser;
use App\Services\Garmin\GarminClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Serves one running activity, and either fails or succeeds the .fit download
 * depending on how it was built.
 */
function fitClient(bool $downloadWorks, bool $returnsEmpty = false): GarminClient
{
    return new class($downloadWorks, $returnsEmpty) implements GarminClient
    {
        /** @var list<int> */
        public array $forgotten = [];

        public int $downloadAttempts = 0;

        public function __construct(private bool $downloadWorks, private bool $returnsEmpty) {}

        public function login(GarminConnection $connection, string $email, string $password): LoginResult
        {
            return new LoginResult('ok');
        }

        public function resumeLoginWithMfa(GarminConnection $connection, string $loginToken, string $code): LoginResult
        {
            return new LoginResult('ok');
        }

        public function forget(GarminConnection $connection): void
        {

            $this->forgotten[] = $connection->id;

        }

        public function status(GarminConnection $connection): ConnectionStatus
        {
            return new ConnectionStatus(true);
        }

        public function activities(GarminConnection $connection, CarbonImmutable $start, CarbonImmutable $end): array
        {
            return [ActivitySummary::fromSidecar([
                'activityId' => 4242,
                'activityType' => ['typeKey' => 'running'],
                'startTimeGMT' => CarbonImmutable::now()->subHours(3)->toDateTimeString(),
                'duration' => 3600,
                'distance' => 10000,
                'averageHR' => 150,
            ])];
        }

        public function downloadFit(GarminConnection $connection, string $activityId): string
        {
            $this->downloadAttempts++;

            if ($this->returnsEmpty) {
                return '';
            }

            if (! $this->downloadWorks) {
                throw new RuntimeException('Garmin sidecar returned HTTP 502');
            }

            return 'FAKE-FIT-BYTES';
        }

        public function wellness(GarminConnection $connection, CarbonImmutable $date): WellnessSnapshot
        {
            return WellnessSnapshot::fromSidecar(['date' => $date->toDateString()]);
        }

        public function pushWorkout(GarminConnection $connection, array $workout, CarbonImmutable $date): string
        {
            return '1';
        }
    };
}

function hrFitParser(): FitParser
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

function garminUser(): User
{
    $user = User::factory()->create();
    HrZoneSettings::create(['user_id' => $user->id, 'max_hr' => 190, 'resting_hr' => 50, 'sex' => 'male']);

    return $user;
}

beforeEach(function () {
    Storage::fake('local');
    $this->app->instance(FitParser::class, hrFitParser());
});

it('records the failure instead of silently storing an activity with no load', function () {
    $this->app->instance(GarminClient::class, fitClient(downloadWorks: false));
    $user = garminUser();
    $connection = GarminConnection::create([
        'user_id' => $user->id,
        'status' => GarminConnection::STATUS_CONNECTED,
        'last_synced_at' => now()->subDay(),
    ]);

    app(SyncGarminAction::class)->handle($connection);

    $activity = Activity::query()->where('user_id', $user->id)->sole();

    expect($activity->fit_path)->toBeNull()
        ->and($activity->trimp)->toBeNull()
        ->and($activity->fit_failed_at)->not->toBeNull()
        ->and($activity->fit_error)->toContain('502');
});

it('recovers the archive and backfills the metrics on a later sync', function () {
    $this->app->instance(GarminClient::class, fitClient(downloadWorks: false));
    $user = garminUser();
    $connection = GarminConnection::create([
        'user_id' => $user->id,
        'status' => GarminConnection::STATUS_CONNECTED,
        'last_synced_at' => now()->subDay(),
    ]);
    app(SyncGarminAction::class)->handle($connection);

    // The activity is now well outside the incremental window, which is exactly
    // the case that used to be unrecoverable.
    $connection->forceFill(['last_synced_at' => now()])->save();
    $this->app->instance(GarminClient::class, fitClient(downloadWorks: true));
    app(SyncGarminAction::class)->handle($connection->fresh());

    $activity = Activity::query()->where('user_id', $user->id)->sole();

    expect($activity->fit_path)->not->toBeNull()
        ->and($activity->fit_failed_at)->toBeNull()
        ->and($activity->fit_error)->toBeNull()
        ->and($activity->trimp)->toBeGreaterThan(0.0)
        ->and($activity->hr_zone_seconds)->not->toBeNull()
        ->and($activity->streams_path)->not->toBeNull();

    Storage::disk('local')->assertExists($activity->fit_path);
});

it('leaves healthy activities untouched by the repair pass', function () {
    $this->app->instance(GarminClient::class, $client = fitClient(downloadWorks: true));
    $user = garminUser();
    $connection = GarminConnection::create([
        'user_id' => $user->id,
        'status' => GarminConnection::STATUS_CONNECTED,
        'last_synced_at' => now()->subDay(),
    ]);

    app(SyncGarminAction::class)->handle($connection);

    // One download for the activity itself, and no repair pass behind it.
    expect($client->downloadAttempts)->toBe(1)
        ->and(Activity::query()->where('user_id', $user->id)->sole()->fit_failed_at)->toBeNull();
});

it('treats an empty .fit response as a failure worth retrying', function () {
    $this->app->instance(GarminClient::class, fitClient(downloadWorks: true, returnsEmpty: true));
    $user = garminUser();
    $connection = GarminConnection::create([
        'user_id' => $user->id,
        'status' => GarminConnection::STATUS_CONNECTED,
        'last_synced_at' => now()->subDay(),
    ]);

    app(SyncGarminAction::class)->handle($connection);

    expect(Activity::query()->where('user_id', $user->id)->sole())
        ->fit_path->toBeNull()
        ->fit_failed_at->not->toBeNull();
});

it('counts the missing archives on the Garmin settings page', function () {
    $this->app->instance(GarminClient::class, fitClient(downloadWorks: false));
    $user = garminUser();
    $connection = GarminConnection::create([
        'user_id' => $user->id,
        'status' => GarminConnection::STATUS_CONNECTED,
        'last_synced_at' => now()->subDay(),
    ]);
    app(SyncGarminAction::class)->handle($connection);

    $this->actingAs($user)
        ->get('/settings/garmin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('stats.missing_archives', 1));
});
