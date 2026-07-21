<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataObjects\LoginResult;
use App\Models\GarminConnection;
use App\Services\Garmin\GarminClient;

class ConnectGarminAction
{
    public function __construct(
        private readonly GarminClient $client,
    ) {}

    public function handle(GarminConnection $connection, string $email, string $password): LoginResult
    {
        $result = $this->client->login($connection, $email, $password);

        if (! $result->isMfaRequired()) {
            $this->markConnected($connection, $result->displayName);
        }

        return $result;
    }

    public function completeMfa(GarminConnection $connection, string $loginToken, string $code): LoginResult
    {
        $result = $this->client->resumeLoginWithMfa($connection, $loginToken, $code);

        $this->markConnected($connection, $result->displayName);

        return $result;
    }

    private function markConnected(GarminConnection $connection, ?string $displayName): void
    {
        $connection->update([
            'status' => GarminConnection::STATUS_CONNECTED,
            'garmin_display_name' => $displayName,
            'sync_error' => null,
        ]);
    }
}
