<?php

namespace App\Models;

use App\Enums\Sport;
use App\Enums\WorkoutType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property Sport $sport
 * @property WorkoutType|null $workout_type
 * @property list<array{repeat: int, intensity: string, duration_min: int, recovery_min: int|null, recovery_intensity: string|null, label: string|null}> $steps
 */
#[Fillable(['user_id', 'name', 'sport', 'workout_type', 'steps'])]
class WorkoutTemplate extends Model
{
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
            'sport' => Sport::class,
            'workout_type' => WorkoutType::class,
            'steps' => 'array',
        ];
    }
}
