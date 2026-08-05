<?php

declare(strict_types=1);

namespace App\Services\Garmin;

use App\DataObjects\ActivitySummary;
use App\DataObjects\ConnectionStatus;
use App\DataObjects\LoginResult;
use App\DataObjects\WellnessSnapshot;
use App\Exceptions\GarminConnectException;
use App\Models\GarminConnection;
use App\Support\Payload;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final readonly class SidecarGarminClient implements GarminClient
{
    /** Seconds a health probe may block a page load for. */
    private const STATUS_TIMEOUT = 5;

    public function __construct(
        private string $baseUrl,
        private string $secret,
        private int $timeout = 120,
    ) {}

    public function login(GarminConnection $connection, string $email, string $password): LoginResult
    {
        $response = $this->translated(fn (): Response => $this->request()->post('/login', [
            'connection_id' => (string) $connection->id,
            'email' => $email,
            'password' => $password,
        ]));

        return LoginResult::fromSidecar(Payload::assoc($response->json()));
    }

    public function resumeLoginWithMfa(GarminConnection $connection, string $loginToken, string $code): LoginResult
    {
        $response = $this->translated(fn (): Response => $this->request()->post('/login/mfa', [
            'connection_id' => (string) $connection->id,
            'login_token' => $loginToken,
            'code' => $code,
        ]));

        return LoginResult::fromSidecar(Payload::assoc($response->json()));
    }

    public function status(GarminConnection $connection): ConnectionStatus
    {
        // A page load waits on this, so it gets its own short timeout rather
        // than the sync-sized default.
        $response = $this->translated(fn (): Response => $this->request()
            ->timeout(self::STATUS_TIMEOUT)
            ->get('/status', ['connection_id' => (string) $connection->id]));

        return ConnectionStatus::fromSidecar(Payload::assoc($response->json()));
    }

    public function forget(GarminConnection $connection): void
    {
        try {
            $this->request()->delete("/connections/{$connection->id}")->throw();
        } catch (ConnectionException $e) {
            throw GarminConnectException::unreachable($e);
        }
    }

    public function activities(GarminConnection $connection, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $response = $this->request()->get('/activities', [
            'connection_id' => (string) $connection->id,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
        ])->throw();

        return array_values(array_map(
            static fn (mixed $activity): ActivitySummary => ActivitySummary::fromSidecar(Payload::assoc($activity)),
            Payload::arr($response->json()),
        ));
    }

    public function downloadFit(GarminConnection $connection, string $activityId): string
    {
        $response = $this->request()
            ->get("/activities/{$activityId}/fit", ['connection_id' => (string) $connection->id])
            ->throw();

        return $response->body();
    }

    public function wellness(GarminConnection $connection, CarbonImmutable $date): WellnessSnapshot
    {
        $response = $this->request()->get('/wellness', [
            'connection_id' => (string) $connection->id,
            'date' => $date->toDateString(),
        ])->throw();

        return WellnessSnapshot::fromSidecar(Payload::assoc($response->json()));
    }

    public function pushWorkout(GarminConnection $connection, array $workout, CarbonImmutable $date): string
    {
        $response = $this->request()->post('/workouts', [
            'connection_id' => (string) $connection->id,
            'sport' => $workout['sport'],
            'name' => $workout['name'],
            'date' => $date->toDateString(),
            'estimated_seconds' => $workout['estimated_seconds'],
            'steps' => $workout['steps'],
        ])->throw();

        return Payload::toStr($response->json('workout_id'));
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['X-Tempo-Secret' => $this->secret])
            ->timeout($this->timeout);
    }

    /**
     * Run a request, translating transport and sidecar failures into a typed
     * GarminConnectException so callers can show a specific message.
     *
     * @param  callable(): Response  $request
     */
    private function translated(callable $request): Response
    {
        try {
            $response = $request();
        } catch (ConnectionException $e) {
            throw GarminConnectException::unreachable($e);
        }

        if ($response->failed()) {
            throw GarminConnectException::fromResponse($response);
        }

        return $response;
    }
}
