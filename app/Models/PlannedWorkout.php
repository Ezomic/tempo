<?php

namespace App\Models;

use App\Enums\Sport;
use App\Enums\WorkoutType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon $date
 * @property Sport $sport
 * @property WorkoutType|null $workout_type
 * @property string $title
 * @property string|null $notes
 * @property array<int, array{0: float, 1: float}>|null $route_geometry
 * @property int|null $route_distance_m
 * @property int|null $route_ascent_m
 * @property string|null $route_kind
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
    'workout_type',
    'title',
    'notes',
    'route_geometry',
    'route_distance_m',
    'route_ascent_m',
    'route_kind',
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

    public function computedDurationMin(): int
    {
        return (int) $this->steps->sum(fn (PlannedWorkoutStep $step): int => $step->totalMinutes());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<PlannedWorkoutStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(PlannedWorkoutStep::class)->orderBy('position');
    }

    public function hasRoute(): bool
    {
        return $this->route_geometry !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'sport' => Sport::class,
            'workout_type' => WorkoutType::class,
            'route_geometry' => 'array',
            'pushed_at' => 'datetime',
        ];
    }
}
