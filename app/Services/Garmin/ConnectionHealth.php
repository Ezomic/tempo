<?php

declare(strict_types=1);

namespace App\Services\Garmin;

use App\Models\GarminConnection;
use App\Support\Payload;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * What the sidecar actually says about a connection, as opposed to what the
 * local status column claims. A Garmin session can expire server-side and the
 * sidecar can be stopped entirely, and neither shows up in the database.
 */
final readonly class ConnectionHealth
{
    public const HEALTHY = 'healthy';

    public const SESSION_EXPIRED = 'session_expired';

    public const SIDECAR_UNREACHABLE = 'sidecar_unreachable';

    /** Seconds a probe result is reused for, so revisiting settings is free. */
    private const CACHE_SECONDS = 60;

    public function __construct(
        private GarminClient $client,
    ) {}

    public function for(GarminConnection $connection): string
    {
        return Payload::toStr(Cache::remember(
            "garmin:health:{$connection->id}",
            self::CACHE_SECONDS,
            fn (): string => $this->probe($connection),
        ));
    }

    public function forget(GarminConnection $connection): void
    {
        Cache::forget("garmin:health:{$connection->id}");
    }

    private function probe(GarminConnection $connection): string
    {
        try {
            return $this->client->status($connection)->connected
                ? self::HEALTHY
                : self::SESSION_EXPIRED;
        } catch (Throwable) {
            return self::SIDECAR_UNREACHABLE;
        }
    }
}
