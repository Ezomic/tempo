<?php

declare(strict_types=1);

use App\Actions\SyncGarminAction;
use App\DataObjects\ConnectionStatus;
use App\DataObjects\LoginResult;
use App\DataObjects\WellnessSnapshot;
use App\Models\GarminConnection;
use App\Models\User;
use App\Models\WellnessDay;
use App\Services\Garmin\GarminClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/**
 * A client that serves wellness for every date except the ones it is told to
 * fail on, and counts what it was asked for.
 */
function wellnessClient(array $failOn): GarminClient
{
    return new class($failOn) implements GarminClient
    {
        /** @var list<string> */
        public array $requested = [];

        public function __construct(private array $failOn) {}

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
            return new ConnectionStatus(true);
        }

        public function activities(GarminConnection $connection, CarbonImmutable $start, CarbonImmutable $end): array
        {
            return [];
        }

        public function downloadFit(GarminConnection $connection, string $activityId): string
        {
            return '';
        }

        public function wellness(GarminConnection $connection, CarbonImmutable $date): WellnessSnapshot
        {
            $iso = $date->toDateString();
            $this->requested[] = $iso;

            if (in_array($iso, $this->failOn, true)) {
                throw new RuntimeException("Garmin sidecar returned HTTP 429 for {$iso}");
            }

            return WellnessSnapshot::fromSidecar([
                'date' => $iso,
                'resting_hr' => ['restingHeartRate' => 48],
            ]);
        }

        public function pushWorkout(GarminConnection $connection, array $workout, CarbonImmutable $date): string
        {
            return '1';
        }
    };
}

function connectedFor(User $user, ?string $lastSyncedAt): GarminConnection
{
    return GarminConnection::create([
        'user_id' => $user->id,
        'status' => GarminConnection::STATUS_CONNECTED,
        'last_synced_at' => $lastSyncedAt,
    ]);
}

beforeEach(function () {
    Storage::fake('local');
});

it('keeps syncing the remaining days when one wellness day fails', function () {
    $failed = now()->subDays(3)->toDateString();
    $this->app->instance(GarminClient::class, wellnessClient([$failed]));

    $user = User::factory()->create();
    $connection = connectedFor($user, now()->subDays(5)->toDateTimeString());

    app(SyncGarminAction::class)->handle($connection);

    expect(WellnessDay::query()->where('user_id', $user->id)->count())->toBeGreaterThan(1)
        ->and(WellnessDay::query()->where('user_id', $user->id)->whereDate('date', $failed)->exists())->toBeFalse();
});

it('completes the sync rather than marking the connection errored', function () {
    $this->app->instance(GarminClient::class, wellnessClient([now()->subDays(2)->toDateString()]));

    $user = User::factory()->create();
    $connection = connectedFor($user, now()->subDays(5)->toDateTimeString());

    app(SyncGarminAction::class)->handle($connection);

    expect($connection->fresh())
        ->sync_status->toBe(GarminConnection::SYNC_IDLE)
        ->sync_error->toBeNull()
        ->last_synced_at->not->toBeNull();
});

it('picks a skipped day back up on the next sync', function () {
    $failed = now()->subDays(3)->toDateString();
    $flaky = wellnessClient([$failed]);
    $this->app->instance(GarminClient::class, $flaky);

    $user = User::factory()->create();
    $connection = connectedFor($user, now()->subDays(5)->toDateTimeString());
    app(SyncGarminAction::class)->handle($connection);

    // Second run, this time with a healthy client. The lookback window has to
    // reach back past last_synced_at for the gap to be reachable at all.
    $healthy = wellnessClient([]);
    $this->app->instance(GarminClient::class, $healthy);
    app(SyncGarminAction::class)->handle($connection->fresh());

    expect($healthy->requested)->toContain($failed)
        ->and(WellnessDay::query()->where('user_id', $user->id)->whereDate('date', $failed)->exists())->toBeTrue();
});

it('does not re-fetch days it already captured', function () {
    $this->app->instance(GarminClient::class, wellnessClient([]));

    $user = User::factory()->create();
    $connection = connectedFor($user, now()->subDays(5)->toDateTimeString());
    app(SyncGarminAction::class)->handle($connection);

    $second = wellnessClient([]);
    $this->app->instance(GarminClient::class, $second);
    app(SyncGarminAction::class)->handle($connection->fresh());

    // Only today is mutable, so a second run right after the first should ask
    // for that one day and nothing else.
    expect($second->requested)->toBe([now()->toDateString()]);
});

it('still fails the sync when no wellness day can be fetched at all', function () {
    $dates = collect(range(0, 14))
        ->map(fn (int $d): string => now()->subDays($d)->toDateString())
        ->all();
    $this->app->instance(GarminClient::class, wellnessClient($dates));

    $user = User::factory()->create();
    $connection = connectedFor($user, now()->subDays(5)->toDateTimeString());

    expect(fn () => app(SyncGarminAction::class)->handle($connection))
        ->toThrow(RuntimeException::class);

    expect($connection->fresh())
        ->sync_status->toBe(GarminConnection::SYNC_ERROR)
        ->sync_error->not->toBeNull();
});
