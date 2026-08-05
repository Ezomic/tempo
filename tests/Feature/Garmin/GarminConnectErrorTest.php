<?php

declare(strict_types=1);

use App\DataObjects\ConnectionStatus;
use App\DataObjects\LoginResult;
use App\DataObjects\WellnessSnapshot;
use App\Enums\GarminFailure;
use App\Exceptions\GarminConnectException;
use App\Models\GarminConnection;
use App\Models\User;
use App\Services\Garmin\GarminClient;
use App\Services\Garmin\SidecarGarminClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function sidecar(): SidecarGarminClient
{
    return new SidecarGarminClient('http://sidecar.test', 'secret', 5);
}

function newConnection(): GarminConnection
{
    return User::factory()->create()->garminConnection()->create();
}

function throwingGarminClient(Throwable $e): GarminClient
{
    return new class($e) implements GarminClient
    {
        /** @var list<int> */
        public array $forgotten = [];

        public function __construct(private Throwable $e) {}

        public function login(GarminConnection $connection, string $email, string $password): LoginResult
        {
            throw $this->e;
        }

        public function resumeLoginWithMfa(GarminConnection $connection, string $loginToken, string $code): LoginResult
        {
            throw $this->e;
        }

        public function forget(GarminConnection $connection): void
        {

            $this->forgotten[] = $connection->id;

        }

        public function status(GarminConnection $connection): ConnectionStatus
        {
            return new ConnectionStatus(false);
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
            throw $this->e;
        }
    };
}

it('maps sidecar sign-in status codes to failure reasons', function (int $status, GarminFailure $reason) {
    Http::fake(['sidecar.test/*' => Http::response('x', $status)]);

    try {
        sidecar()->login(newConnection(), 'a@b.test', 'secret');
        $this->fail('Expected a GarminConnectException.');
    } catch (GarminConnectException $e) {
        expect($e->reason)->toBe($reason);
    }
})->with([
    'auth' => [401, GarminFailure::AuthFailed],
    'rate limit' => [429, GarminFailure::RateLimited],
    'garmin down' => [502, GarminFailure::GarminUnreachable],
    'unknown' => [500, GarminFailure::Unknown],
]);

it('maps a sidecar connection failure to Unreachable', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    try {
        sidecar()->login(newConnection(), 'a@b.test', 'secret');
        $this->fail('Expected a GarminConnectException.');
    } catch (GarminConnectException $e) {
        expect($e->reason)->toBe(GarminFailure::Unreachable);
    }
});

it('shows the specific message for each connect failure', function (GarminFailure $reason) {
    $this->app->instance(GarminClient::class, throwingGarminClient(new GarminConnectException($reason)));
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/settings/garmin/connect', ['email' => 'a@b.test', 'password' => 'secret'])
        ->assertRedirect()
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toBe($reason->message());
})->with([
    'auth' => [GarminFailure::AuthFailed],
    'rate limit' => [GarminFailure::RateLimited],
    'unreachable' => [GarminFailure::Unreachable],
    'garmin down' => [GarminFailure::GarminUnreachable],
]);
