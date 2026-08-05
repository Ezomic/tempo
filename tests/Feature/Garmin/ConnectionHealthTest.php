<?php

declare(strict_types=1);

use App\DataObjects\ConnectionStatus;
use App\DataObjects\LoginResult;
use App\DataObjects\WellnessSnapshot;
use App\Exceptions\GarminConnectException;
use App\Models\GarminConnection;
use App\Models\User;
use App\Services\Garmin\ConnectionHealth;
use App\Services\Garmin\GarminClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  ConnectionStatus|Throwable  $outcome  what the sidecar probe does
 */
function probingClient(ConnectionStatus|Throwable $outcome): GarminClient
{
    return new class($outcome) implements GarminClient
    {
        public int $probes = 0;

        public function __construct(private ConnectionStatus|Throwable $outcome) {}

        public function status(GarminConnection $connection): ConnectionStatus
        {
            $this->probes++;

            if ($this->outcome instanceof Throwable) {
                throw $this->outcome;
            }

            return $this->outcome;
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
            return WellnessSnapshot::fromSidecar(['date' => $date->toDateString()]);
        }

        public function pushWorkout(GarminConnection $connection, array $workout, CarbonImmutable $date): string
        {
            return '1';
        }
    };
}

function connectedUserFor(): User
{
    $user = User::factory()->create();
    GarminConnection::create(['user_id' => $user->id, 'status' => GarminConnection::STATUS_CONNECTED]);

    return $user;
}

beforeEach(function () {
    Cache::flush();
});

it('reports a live connection as healthy', function () {
    $this->app->instance(GarminClient::class, probingClient(new ConnectionStatus(true, 'Test Athlete')));
    $user = connectedUserFor();

    expect(app(ConnectionHealth::class)->for($user->garminConnection))
        ->toBe(ConnectionHealth::HEALTHY);
});

it('reports an expired Garmin session even though the local status says connected', function () {
    $this->app->instance(GarminClient::class, probingClient(new ConnectionStatus(false)));
    $user = connectedUserFor();

    expect($user->garminConnection->status)->toBe(GarminConnection::STATUS_CONNECTED)
        ->and(app(ConnectionHealth::class)->for($user->garminConnection))
        ->toBe(ConnectionHealth::SESSION_EXPIRED);
});

it('reports an unreachable sidecar', function () {
    $this->app->instance(GarminClient::class, probingClient(GarminConnectException::unreachable()));
    $user = connectedUserFor();

    expect(app(ConnectionHealth::class)->for($user->garminConnection))
        ->toBe(ConnectionHealth::SIDECAR_UNREACHABLE);
});

it('caches the probe so revisiting settings does not hit the sidecar again', function () {
    $client = probingClient(new ConnectionStatus(true));
    $this->app->instance(GarminClient::class, $client);
    $user = connectedUserFor();
    $health = app(ConnectionHealth::class);

    $health->for($user->garminConnection);
    $health->for($user->garminConnection);

    expect($client->probes)->toBe(1);

    $health->forget($user->garminConnection);
    $health->for($user->garminConnection);

    expect($client->probes)->toBe(2);
});

it('surfaces the health state on the settings page', function () {
    $this->app->instance(GarminClient::class, probingClient(new ConnectionStatus(false)));
    $user = connectedUserFor();

    $this->actingAs($user)
        ->get('/settings/garmin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('connection.health', ConnectionHealth::SESSION_EXPIRED));
});

it('does not probe when there is no connected account', function () {
    $client = probingClient(new ConnectionStatus(true));
    $this->app->instance(GarminClient::class, $client);
    $user = User::factory()->create();
    GarminConnection::create(['user_id' => $user->id, 'status' => GarminConnection::STATUS_DISCONNECTED]);

    $this->actingAs($user)
        ->get('/settings/garmin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('connection.health', null));

    expect($client->probes)->toBe(0);
});

it('does not let a down sidecar break the settings page', function () {
    config(['services.garmin_sidecar.url' => 'http://sidecar.test']);
    Http::fake(fn () => throw new ConnectionException('refused'));
    $user = connectedUserFor();

    $this->actingAs($user)
        ->get('/settings/garmin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('connection.health', ConnectionHealth::SIDECAR_UNREACHABLE));
});
