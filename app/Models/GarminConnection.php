<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $status
 * @property string|null $garmin_display_name
 * @property string $sync_status
 * @property Carbon|null $sync_status_since
 * @property string|null $sync_error
 * @property Carbon|null $last_synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable([
    'user_id',
    'status',
    'garmin_display_name',
    'sync_status',
    'sync_status_since',
    'sync_error',
    'last_synced_at',
])]
class GarminConnection extends Model
{
    public const STATUS_DISCONNECTED = 'disconnected';

    public const STATUS_CONNECTED = 'connected';

    public const SYNC_IDLE = 'idle';

    public const SYNC_SYNCING = 'syncing';

    public const SYNC_ERROR = 'error';

    /**
     * A run that has not reported back in this long was killed rather than
     * finished: SyncGarminJob times out at 1800s, and the rest is slack for the
     * job sitting in the queue. Without this, a killed worker leaves the row on
     * "syncing" forever, since only the job itself ever writes that field.
     */
    public const SYNC_STALE_AFTER_MINUTES = 35;

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED;
    }

    public function syncIsStale(): bool
    {
        return $this->sync_status === self::SYNC_SYNCING
            && $this->sync_status_since !== null
            && $this->sync_status_since->lessThan(now()->subMinutes(self::SYNC_STALE_AFTER_MINUTES));
    }

    public function effectiveSyncStatus(): string
    {
        return $this->syncIsStale() ? self::SYNC_ERROR : $this->sync_status;
    }

    public function syncErrorMessage(): ?string
    {
        return $this->syncIsStale()
            ? 'The last sync stopped before it finished. Start it again.'
            : $this->sync_error;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sync_status_since' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }
}
