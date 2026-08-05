<?php

declare(strict_types=1);

use App\DataObjects\ConnectionStatus;
use App\DataObjects\LoginResult;
use App\DataObjects\WellnessSnapshot;
use App\Enums\GarminFailure;
use App\Exceptions\GarminConnectException;
use App\Models\Activity;
use App\Models\GarminConnection;
use App\Models\User;
use App\Services\Garmin\GarminClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function forgettingClient(bool $sidecarUp = true): GarminClient
{
    return new class($sidecarUp) implements GarminClient
    {
        /** @var list<int> */
        public array $forgotten = [];

        public function __construct(private bool $sidecarUp) {}

        public function forget(GarminConnection $connection): void
        {
            if (! $this->sidecarUp) {
                throw GarminConnectException::unreachable();
            }

            $this->forgotten[] = $connection->id;
        }

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
            return WellnessSnapshot::fromSidecar(['date' => $date->toDateString()]);
        }

        public function pushWorkout(GarminConnection $connection, array $workout, CarbonImmutable $date): string
        {
            return '1';
        }
    };
}

function userWithGarminData(): User
{
    $user = User::factory()->create();
    GarminConnection::create(['user_id' => $user->id, 'status' => GarminConnection::STATUS_CONNECTED]);
    Storage::disk('local')->put("garmin/fit/{$user->id}/111.fit", 'FIT');
    Storage::disk('local')->put("garmin/streams/{$user->id}/111.json", '{}');

    return $user;
}

beforeEach(function () {
    Storage::fake('local');
});

it('ends the Garmin session on the sidecar when disconnecting', function () {
    $client = forgettingClient();
    $this->app->instance(GarminClient::class, $client);
    $user = userWithGarminData();
    $connectionId = $user->garminConnection->id;

    $this->actingAs($user)->delete('/settings/garmin')->assertRedirect();

    expect($client->forgotten)->toBe([$connectionId])
        ->and($user->fresh()->garminConnection)->toBeNull();
});

it('still disconnects locally when the sidecar cannot be reached', function () {
    $this->app->instance(GarminClient::class, forgettingClient(sidecarUp: false));
    $user = userWithGarminData();

    $this->actingAs($user)->delete('/settings/garmin')->assertRedirect();

    expect($user->fresh()->garminConnection)->toBeNull()
        ->and(session('status'))->toContain('could not be ended');
});

it('ends the session and purges archived files when the account is deleted', function () {
    $client = forgettingClient();
    $this->app->instance(GarminClient::class, $client);
    $user = userWithGarminData();
    $connectionId = $user->garminConnection->id;
    Activity::create([
        'user_id' => $user->id,
        'external_id' => '111',
        'sport' => 'run',
        'started_at' => now(),
        'fit_path' => "garmin/fit/{$user->id}/111.fit",
    ]);

    $this->actingAs($user)
        ->delete('/settings/profile', ['email' => $user->email])
        ->assertSessionHasNoErrors();

    expect($client->forgotten)->toBe([$connectionId])
        ->and(User::find($user->id))->toBeNull();

    Storage::disk('local')->assertMissing("garmin/fit/{$user->id}/111.fit");
    Storage::disk('local')->assertMissing("garmin/streams/{$user->id}/111.json");
});

it('sends a delete to the sidecar for the connection', function () {
    config(['services.garmin_sidecar.url' => 'http://sidecar.test']);
    Http::fake(['sidecar.test/*' => Http::response(['status' => 'ok', 'removed' => true])]);
    $user = User::factory()->create();
    $connection = GarminConnection::create([
        'user_id' => $user->id,
        'status' => GarminConnection::STATUS_CONNECTED,
    ]);

    app(GarminClient::class)->forget($connection);

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && str_ends_with($request->url(), "/connections/{$connection->id}")
        && $request->hasHeader('X-Tempo-Secret'));
});

it('reports an unreachable sidecar as a typed failure', function () {
    Http::fake(fn () => throw new ConnectionException('refused'));
    $user = User::factory()->create();
    $connection = GarminConnection::create(['user_id' => $user->id]);

    expect(fn () => app(GarminClient::class)->forget($connection))
        ->toThrow(GarminConnectException::class);

    try {
        app(GarminClient::class)->forget($connection);
    } catch (GarminConnectException $e) {
        expect($e->reason)->toBe(GarminFailure::Unreachable);
    }
});
