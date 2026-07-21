<?php

declare(strict_types=1);

use App\Actions\SyncGarminAction;
use App\DataObjects\ActivitySummary;
use App\DataObjects\ConnectionStatus;
use App\DataObjects\LoginResult;
use App\DataObjects\ParsedActivity;
use App\DataObjects\WellnessSnapshot;
use App\Enums\Sport;
use App\Models\Activity;
use App\Models\GarminConnection;
use App\Models\HrZoneSettings;
use App\Models\User;
use App\Models\WellnessDay;
use App\Services\Garmin\FitParser;
use App\Services\Garmin\GarminClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

function fakeGarminClient(): GarminClient
{
    return new class implements GarminClient
    {
        public function login(GarminConnection $connection, string $email, string $password): LoginResult
        {
            return new LoginResult('ok');
        }

        public function resumeLoginWithMfa(GarminConnection $connection, string $loginToken, string $code): LoginResult
        {
            return new LoginResult('ok');
        }

        public function status(GarminConnection $connection): ConnectionStatus
        {
            return new ConnectionStatus(true, 'Test Athlete');
        }

        public function activities(GarminConnection $connection, CarbonImmutable $start, CarbonImmutable $end): array
        {
            return [ActivitySummary::fromSidecar([
                'activityId' => 999,
                'activityType' => ['typeKey' => 'running'],
                'startTimeGMT' => '2026-07-20 06:00:00',
                'duration' => 3600,
                'movingDuration' => 3500,
                'distance' => 10000,
                'averageHR' => 150,
                'maxHR' => 165,
                'elevationGain' => 80,
                'averageSpeed' => 2.8,
                'calories' => 600,
            ])];
        }

        public function downloadFit(GarminConnection $connection, string $activityId): string
        {
            return 'FAKE-FIT-BYTES';
        }

        public function wellness(GarminConnection $connection, CarbonImmutable $date): WellnessSnapshot
        {
            return WellnessSnapshot::fromSidecar([
                'date' => $date->toDateString(),
                'hrv' => ['hrvSummary' => ['status' => 'BALANCED', 'lastNightAvg' => 65]],
                'resting_hr' => ['restingHeartRate' => 48],
                'stress' => ['avgStressLevel' => 30],
            ]);
        }
    };
}

function fakeFitParser(): FitParser
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

beforeEach(function () {
    Storage::fake('local');

    $this->app->instance(GarminClient::class, fakeGarminClient());
    $this->app->instance(FitParser::class, fakeFitParser());
});

it('stores activities with computed TRIMP and archives the FIT file', function () {
    $user = User::factory()->create();
    HrZoneSettings::create(['user_id' => $user->id, 'max_hr' => 190, 'resting_hr' => 50, 'sex' => 'male']);
    $connection = GarminConnection::create([
        'user_id' => $user->id,
        'status' => GarminConnection::STATUS_CONNECTED,
        'last_synced_at' => now()->subDay(),
    ]);

    app(SyncGarminAction::class)->handle($connection);

    $activity = Activity::query()->where('user_id', $user->id)->sole();

    expect($activity->external_id)->toBe('999')
        ->and($activity->sport)->toBe(Sport::Run)
        ->and($activity->distance_m)->toBe(10000.0)
        ->and($activity->trimp)->toBeGreaterThan(1.7)->toBeLessThan(1.9)
        ->and($activity->hr_zone_seconds[3])->toBe(60)
        ->and($activity->fit_path)->not->toBeNull();

    Storage::disk('local')->assertExists($activity->fit_path);
});

it('stores wellness days from the sidecar snapshot', function () {
    $user = User::factory()->create();
    $connection = GarminConnection::create([
        'user_id' => $user->id,
        'status' => GarminConnection::STATUS_CONNECTED,
        'last_synced_at' => now()->subDay(),
    ]);

    app(SyncGarminAction::class)->handle($connection);

    $wellness = WellnessDay::query()->where('user_id', $user->id)->latest('date')->first();

    expect($wellness)->not->toBeNull()
        ->and($wellness->resting_hr)->toBe(48)
        ->and($wellness->hrv_last_night_ms)->toBe(65)
        ->and($wellness->hrv_status?->value)->toBe('balanced');

    $connection->refresh();
    expect($connection->sync_status)->toBe(GarminConnection::SYNC_IDLE)
        ->and($connection->last_synced_at)->not->toBeNull();
});

it('marks the connection errored when the sync throws', function () {
    $user = User::factory()->create();
    $connection = GarminConnection::create([
        'user_id' => $user->id,
        'status' => GarminConnection::STATUS_DISCONNECTED,
    ]);

    expect(fn () => app(SyncGarminAction::class)->handle($connection))
        ->toThrow(RuntimeException::class);
});
