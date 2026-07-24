<?php

namespace App\Models;

use App\Enums\Intensity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $planned_workout_id
 * @property int $position
 * @property int $repeat
 * @property Intensity $intensity
 * @property int $duration_min
 * @property int|null $recovery_min
 * @property Intensity|null $recovery_intensity
 * @property string|null $label
 */
#[Fillable([
    'planned_workout_id',
    'position',
    'repeat',
    'intensity',
    'duration_min',
    'recovery_min',
    'recovery_intensity',
    'label',
])]
class PlannedWorkoutStep extends Model
{
    public $timestamps = false;

    public function totalMinutes(): int
    {
        return $this->repeat * ($this->duration_min + ($this->recovery_min ?? 0));
    }

    /**
     * @return BelongsTo<PlannedWorkout, $this>
     */
    public function plannedWorkout(): BelongsTo
    {
        return $this->belongsTo(PlannedWorkout::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'intensity' => Intensity::class,
            'recovery_intensity' => Intensity::class,
        ];
    }
}
