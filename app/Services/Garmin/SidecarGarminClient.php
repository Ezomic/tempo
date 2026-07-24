<?php

declare(strict_types=1);

namespace App\Services\Garmin;

use App\DataObjects\ActivitySummary;
use App\DataObjects\ConnectionStatus;
use App\DataObjects\LoginResult;
use App\DataObjects\WellnessSnapshot;
use App\Exceptions\GarminConnectException;
use App\Models\GarminConnection;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final readonly class SidecarGarminClient implements GarminClient
{
    public function __construct(
        private string $baseUrl,
        private string $secret,
        private int $timeout = 120,
    ) {}

    public function login(GarminConnection $connection, string $email, string $password): LoginResult
    {
        $response = $this->authRequest(fn (): Response => $this->request()->post('/login', [
            'connection_id' => (string) $connection->id,
            'email' => $email,
            'password' => $password,
        ]));

        return LoginResult::fromSidecar($response->json());
    }

    public function resumeLoginWithMfa(GarminConnection $connection, string $loginToken, string $code): LoginResult
    {
        $response = $this->authRequest(fn (): Response => $this->request()->post('/login/mfa', [
            'connection_id' => (string) $connection->id,
            'login_token' => $loginToken,
            'code' => $code,
        ]));

        return LoginResult::fromSidecar($response->json());
    }

    public function status(GarminConnection $connection): ConnectionStatus
    {
        $response = $this->request()
            ->get('/status', ['connection_id' => (string) $connection->id])
            ->throw();

        return ConnectionStatus::fromSidecar($response->json());
    }

    public function activities(GarminConnection $connection, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $response = $this->request()->get('/activities', [
            'connection_id' => (string) $connection->id,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
        ])->throw();

        return array_map(
            static fn (array $activity): ActivitySummary => ActivitySummary::fromSidecar($activity),
            $response->json(),
        );
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

        return WellnessSnapshot::fromSidecar($response->json());
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['X-Tempo-Secret' => $this->secret])
            ->timeout($this->timeout);
    }

    /**
     * Run a sign-in request, translating transport and sidecar failures into a
     * typed GarminConnectException so callers can show a specific message.
     *
     * @param  callable(): Response  $request
     */
    private function authRequest(callable $request): Response
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
