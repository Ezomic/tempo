<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Services\Garmin\GarminClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DisconnectGarminAction
{
    public function __construct(
        private readonly GarminClient $client,
    ) {}

    /**
     * Take the connection down everywhere: the Garmin session on the sidecar
     * first, then the local row. Returns false when the sidecar could not be
     * reached, so the caller can tell the athlete their Garmin session may still
     * be live even though Tempo has forgotten it.
     */
    public function handle(User $user): bool
    {
        $connection = $user->garminConnection;

        if ($connection === null) {
            return true;
        }

        $forgotten = true;

        try {
            $this->client->forget($connection);
        } catch (Throwable $e) {
            $forgotten = false;
            Log::warning('Could not drop the Garmin session on the sidecar', [
                'user_id' => $user->id,
                'connection_id' => $connection->id,
                'reason' => $e->getMessage(),
            ]);
        }

        // Deleting the row regardless: leaving a connection the athlete has
        // asked to remove is worse than an orphan token directory, which the
        // sidecar log now names.
        $connection->delete();

        return $forgotten;
    }

    /**
     * Everything Garmin ever wrote for this user, for account deletion. The DB
     * rows cascade; the archived files on disk do not.
     */
    public function purgeArchives(User $user): void
    {
        Storage::disk('local')->deleteDirectory("garmin/fit/{$user->id}");
        Storage::disk('local')->deleteDirectory("garmin/streams/{$user->id}");
    }
}
