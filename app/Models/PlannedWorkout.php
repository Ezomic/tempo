<?php

namespace App\Models;

use App\Enums\Sport;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon $date
 * @property Sport $sport
 * @property string $title
 * @property string|null $notes
 * @property int|null $duration_min
 * @property string|null $chronos_event_id
 * @property string|null $chronos_url
 * @property Carbon|null $pushed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'date',
    'sport',
    'title',
    'notes',
    'duration_min',
    'chronos_event_id',
    'chronos_url',
    'pushed_at',
])]
class PlannedWorkout extends Model
{
    public function isPushed(): bool
    {
        return $this->pushed_at !== null;
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
            'date' => 'date',
            'sport' => Sport::class,
            'pushed_at' => 'datetime',
        ];
    }
}
